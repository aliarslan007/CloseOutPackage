<?php

use App\Domains\ChangeOrders\Enums\ChangeOrderStatus;
use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\ChangeOrders\Models\ChangeOrderItem;
use App\Domains\EntityFeedback\Enums\FeedbackEntityType;
use App\Domains\EntityFeedback\Enums\FeedbackRoundStatus;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\EntityFeedback\Notifications\FeedbackAcknowledgedNotification;
use App\Domains\EntityFeedback\Notifications\FeedbackFollowUpNotification;
use App\Domains\EntityFeedback\Presenters\FeedbackRoundPresenter;
use App\Domains\Photos\Models\Photo;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/**
 * 1. A change order is manager-authored, so technician delegation is not offered.
 */
it('refuses technician delegation on a change order', function (): void {
    $fixture = feedbackFixture();
    $changeOrder = ChangeOrder::factory()->create([
        'project_id' => $fixture['project']->id,
    ]);

    $round = EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::CHANGE_ORDER->value,
        'entity_id' => $changeOrder->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::SUBMITTED->value,
        'opened_by' => $fixture['manager']->id,
    ]);

    expect($fixture['manager'])->not->toBeNull();

    $this->actingAs($fixture['manager'])
        ->post(route('entity-feedback.rounds.forward-technician', $round), [
            'assigned_to_user_id' => $fixture['admin']->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($round->fresh()->assigned_to_user_id)->toBeNull();
})->group('delegation-scope');

it('still allows technician delegation on a close-out package', function (): void {
    $fixture = feedbackFixture();
    $round = packageRound($fixture, FeedbackRoundStatus::SUBMITTED);
    $tech = User::factory()->create()
        ->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->actingAs($fixture['manager'])
        ->post(route('entity-feedback.rounds.forward-technician', $round), [
            'assigned_to_user_id' => $tech->id,
        ])
        ->assertRedirect();

    expect($round->fresh()->assigned_to_user_id)->toBe($tech->id);
})->group('delegation-scope');

/**
 * 2. The manager acknowledges, which notifies the customer and marks the round
 *    as picked up.
 */
it('lets the owning manager acknowledge and notifies the customer', function (): void {
    Notification::fake();

    $fixture = feedbackFixture();
    $round = packageRound($fixture, FeedbackRoundStatus::SUBMITTED);

    $this->actingAs($fixture['manager'])
        ->post(route('entity-feedback.rounds.acknowledge', $round))
        ->assertRedirect();

    $round->refresh();

    expect($round->isAcknowledged())->toBeTrue()
        ->and($round->manager_acknowledged_by)->toBe($fixture['manager']->id);

    Notification::assertSentTo(
        $fixture['customer'],
        FeedbackAcknowledgedNotification::class
    );
})->group('acknowledge');

it('stops a second acknowledgement and a non-owning manager', function (): void {
    $fixture = feedbackFixture();
    $round = packageRound($fixture, FeedbackRoundStatus::SUBMITTED);

    // A manager who does not own the project cannot promise work on it.
    $otherManager = User::factory()->create()
        ->assignRole(Role::MANAGER->value);

    $this->actingAs($otherManager)
        ->post(route('entity-feedback.rounds.acknowledge', $round))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($round->fresh()->isAcknowledged())->toBeFalse();

    $this->actingAs($fixture['manager'])
        ->post(route('entity-feedback.rounds.acknowledge', $round))
        ->assertRedirect();

    // Acknowledging twice is not a second acknowledgement.
    $this->actingAs($fixture['manager'])
        ->post(route('entity-feedback.rounds.acknowledge', $round))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($round->fresh()->manager_acknowledged_by)->toBe($fixture['manager']->id);
})->group('acknowledge');

it('refuses acknowledgement of a round the customer has not submitted', function (): void {
    $fixture = feedbackFixture();
    $round = packageRound($fixture, FeedbackRoundStatus::OPEN);

    $this->actingAs($fixture['manager'])
        ->post(route('entity-feedback.rounds.acknowledge', $round))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($round->fresh()->isAcknowledged())->toBeFalse();
})->group('acknowledge');

/**
 * 3. The customer can reply to an answer, which returns the round to the manager
 *    instead of forcing accept-or-nothing.
 */
it('lets the customer follow up on a resolution and returns the round to the manager', function (): void {
    Notification::fake();

    $fixture = feedbackFixture();
    $round = packageRound($fixture, FeedbackRoundStatus::RESOLVED);
    $round->update(['resolved_at' => now(), 'resolved_by' => $fixture['manager']->id]);

    $this->actingAs($fixture['customer'])
        ->post(route('entity-feedback.notes.store', $round), [
            'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
            'entity_id' => $fixture['package']->id,
            'body' => 'That is not what I meant.',
            'source_photo_type' => 'follow_up',
        ])
        ->assertRedirect();

    $round->refresh();

    expect($round->status)->toBe(FeedbackRoundStatus::SUBMITTED)
        ->and($round->resolved_at)->toBeNull()
        // A new question deserves a fresh acknowledgement.
        ->and($round->isAcknowledged())->toBeFalse()
        ->and($round->notes()->count())->toBe(1);

    Notification::assertSentTo(
        $fixture['manager'],
        FeedbackFollowUpNotification::class
    );
})->group('follow-up');

it('keeps an already published answer readable when the customer follows up', function (): void {
    $presenter = app(FeedbackRoundPresenter::class);
    $fixture = feedbackFixture();
    $round = packageRound($fixture, FeedbackRoundStatus::RESOLVED);

    $note = packageNote($round, $fixture, 'First question');
    $note->update([
        'manager_response' => 'PUBLISHED ANSWER',
        'resolved_at' => now(),
        'resolved_by' => $fixture['manager']->id,
        'response_sent_at' => now(),
    ]);

    // The customer follows up, so the round is back in the manager's queue.
    $round->update([
        'status' => FeedbackRoundStatus::SUBMITTED->value,
        'resolved_at' => null,
        'resolved_by' => null,
    ]);

    $view = $presenter->present($round->fresh()->load('notes.author'), $fixture['customer']);

    expect($view['notes'][0]['manager_response'])->toBe('PUBLISHED ANSWER')
        ->and($view['notes'][0]['is_resolved'])->toBeTrue();
})->group('follow-up');

it('refuses a customer note on an accepted round', function (): void {
    $fixture = feedbackFixture();
    $round = packageRound($fixture, FeedbackRoundStatus::ACCEPTED);

    $this->actingAs($fixture['customer'])
        ->post(route('entity-feedback.notes.store', $round), [
            'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
            'entity_id' => $fixture['package']->id,
            'body' => 'Too late',
            'source_photo_type' => 'follow_up',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($round->notes()->count())->toBe(0);
})->group('follow-up');

/**
 * 4. Only pressing send publishes a draft answer.
 */
it('publishes a draft answer only when the resolution is sent', function (): void {
    $presenter = app(FeedbackRoundPresenter::class);
    $fixture = feedbackFixture();
    $round = packageRound($fixture, FeedbackRoundStatus::SUBMITTED);

    $note = packageNote($round, $fixture, 'Question');
    $note->update([
        'manager_response' => 'DRAFT ANSWER',
        'resolved_at' => now(),
        'resolved_by' => $fixture['manager']->id,
    ]);

    // Written but not sent: the manager sees it, the customer must not.
    expect($note->isResponseSent())->toBeFalse();
    $draft = $presenter->present($round->fresh()->load('notes.author'), $fixture['customer']);
    expect($draft['notes'][0])->not->toHaveKey('manager_response')
        // Who drafted it is part of the draft, not the customer's business yet.
        ->and($draft['notes'][0])->not->toHaveKey('resolved_by_user');

    $this->actingAs($fixture['manager'])
        ->post(route('entity-feedback.rounds.resolve-all', $round))
        ->assertRedirect();

    expect($note->fresh()->isResponseSent())->toBeTrue();

    $sent = $presenter->present($round->fresh()->load('notes.author'), $fixture['customer']);
    expect($sent['notes'][0]['manager_response'])->toBe('DRAFT ANSWER')
        // A published answer is attributed to the manager who wrote it.
        ->and($sent['notes'][0]['resolved_by_user']['name'])->toBe($fixture['manager']->name);
})->group('publish-on-send');

/**
 * A note that was resolved before response_sent_at existed reads as "never
 * published" by default, which hid answers the customer had already been sent:
 * the manager saw "Answered" and the customer saw nothing. The backfill
 * migration repairs it, and this test fails if the repair is ever dropped.
 */
it('keeps an answer published before the sent-marker existed visible', function (): void {
    $presenter = app(FeedbackRoundPresenter::class);
    $fixture = feedbackFixture();

    $round = packageRound($fixture, FeedbackRoundStatus::RESOLVED);
    $round->update(['resolved_at' => now(), 'resolved_by' => $fixture['manager']->id]);

    $note = packageNote($round, $fixture, 'Already sent before the column existed');
    $note->update([
        'manager_response' => 'ALREADY SENT ANSWER',
        'resolved_at' => now(),
        'resolved_by' => $fixture['manager']->id,
    ]);

    // Run the backfill exactly as the migration does.
    $path = database_path('migrations');
    $file = collect(glob($path.'/*_backfill_published_feedback_responses.php'))->last();

    expect($file)->not->toBeNull('The backfill migration must exist.');

    $migration = require $file;
    $migration->up();

    $view = $presenter->present($round->fresh()->load('notes.author'), $fixture['customer']);

    expect($note->fresh()->isResponseSent())->toBeTrue()
        ->and($view['notes'][0]['manager_response'])->toBe('ALREADY SENT ANSWER')
        ->and($view['notes'][0]['is_resolved'])->toBeTrue();
})->group('publish-on-send');

it('never backfills a draft answer on a round still in progress', function (): void {
    $presenter = app(FeedbackRoundPresenter::class);
    $fixture = feedbackFixture();

    // The manager has answered one note but has NOT sent the round yet.
    $round = packageRound($fixture, FeedbackRoundStatus::SUBMITTED);
    $note = packageNote($round, $fixture, 'Working on it');
    $note->update([
        'manager_response' => 'DRAFT, NOT SENT',
        'resolved_at' => now(),
        'resolved_by' => $fixture['manager']->id,
    ]);

    $file = collect(glob(database_path('migrations').'/*_backfill_published_feedback_responses.php'))->last();
    (require $file)->up();

    expect($note->fresh()->isResponseSent())->toBeFalse();

    $view = $presenter->present($round->fresh()->load('notes.author'), $fixture['customer']);
    expect($view['notes'][0])->not->toHaveKey('manager_response');
})->group('publish-on-send');

/**
 * A second comment about the same photo is a continuation of that photo's
 * conversation, not a separate query. Without the link, a customer and manager
 * discussing one image looked like two unrelated notes.
 */
it('links a reply on the same photo into that conversation', function (): void {
    Notification::fake();

    $fixture = feedbackFixture();
    $round = packageRound($fixture, FeedbackRoundStatus::OPEN);
    $photo = Photo::factory()->create(['closeout_package_id' => $fixture['package']->id]);

    $first = $round->notes()->create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
        'entity_id' => $fixture['package']->id,
        'source_photo_id' => $photo->id,
        'source_photo_type' => 'photo',
        'body' => 'The conduit is not seated.',
        'created_by' => $fixture['customer']->id,
    ]);

    $this->actingAs($fixture['customer'])
        ->post(route('entity-feedback.notes.store', $round), [
            'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
            'entity_id' => $fixture['package']->id,
            'source_photo_id' => $photo->id,
            'source_photo_type' => 'photo',
            'body' => 'I meant the one behind the panel.',
        ])
        ->assertRedirect();

    $second = $round->notes()->orderByDesc('id')->first();

    expect($second->reply_to_note_id)->toBe($first->id)
        ->and($second->source_photo_id)->toBe($photo->id);
})->group('photo-threads');

it('keeps notes on different photos in separate conversations', function (): void {
    $fixture = feedbackFixture();
    $round = packageRound($fixture, FeedbackRoundStatus::OPEN);

    $firstPhoto = Photo::factory()->create(['closeout_package_id' => $fixture['package']->id]);
    $secondPhoto = Photo::factory()->create(['closeout_package_id' => $fixture['package']->id]);

    $first = $round->notes()->create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
        'entity_id' => $fixture['package']->id,
        'source_photo_id' => $firstPhoto->id,
        'source_photo_type' => 'photo',
        'body' => 'On photo ten',
        'created_by' => $fixture['customer']->id,
    ]);

    $this->actingAs($fixture['customer'])
        ->post(route('entity-feedback.notes.store', $round), [
            'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
            'entity_id' => $fixture['package']->id,
            'source_photo_id' => $secondPhoto->id,
            'source_photo_type' => 'photo',
            'body' => 'On photo twenty',
        ])
        ->assertRedirect();

    $second = $round->notes()->orderByDesc('id')->first();

    expect($second->reply_to_note_id)->toBeNull()
        ->and($second->source_photo_id)->toBe($secondPhoto->id)
        ->and($first->reply_to_note_id)->toBeNull();
})->group('photo-threads');

it('refuses a reply target that is not part of the same round', function (): void {
    $fixture = feedbackFixture();
    $round = packageRound($fixture, FeedbackRoundStatus::OPEN);

    $otherRound = packageRound($fixture, FeedbackRoundStatus::OPEN);
    $foreign = $otherRound->notes()->create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
        'entity_id' => $fixture['package']->id,
        'body' => 'Elsewhere',
        'created_by' => $fixture['customer']->id,
    ]);

    $this->actingAs($fixture['customer'])
        ->post(route('entity-feedback.notes.store', $round), [
            'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
            'entity_id' => $fixture['package']->id,
            'body' => 'Trying to reply across rounds',
            'reply_to_note_id' => $foreign->id,
        ])
        ->assertRedirect();

    $note = $round->notes()->orderByDesc('id')->first();

    // Ignored rather than honoured, so a thread cannot be grafted across rounds.
    expect($note->reply_to_note_id)->toBeNull();
})->group('photo-threads');

/**
 * A customer's first note is posted to the open-round endpoint, because there is
 * no round yet. Creating only the round silently dropped it: the customer
 * annotated, saved, and nothing was stored.
 */
it('stores the first note that arrives with the round being opened', function (): void {
    $fixture = feedbackFixture();
    $photo = Photo::factory()->create(['closeout_package_id' => $fixture['package']->id]);

    $this->actingAs($fixture['customer'])
        ->post(route('entity-feedback.rounds.open'), [
            'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
            'entity_id' => $fixture['package']->id,
            'body' => 'First note, sent before any round existed.',
            'source_photo_id' => $photo->id,
            'source_photo_type' => 'photo',
        ])
        ->assertRedirect();

    $round = EntityFeedbackRound::query()
        ->where('entity_type', FeedbackEntityType::CLOSEOUT_PACKAGE->value)
        ->where('entity_id', $fixture['package']->id)
        ->latest('id')
        ->first();

    expect($round)->not->toBeNull()
        ->and($round->notes()->count())->toBe(1)
        ->and($round->notes()->first()->body)->toBe('First note, sent before any round existed.')
        ->and($round->notes()->first()->source_photo_id)->toBe($photo->id);
})->group('first-note');

it('still opens a bare round when no note is included', function (): void {
    $fixture = feedbackFixture();

    $this->actingAs($fixture['manager'])
        ->post(route('entity-feedback.rounds.open'), [
            'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
            'entity_id' => $fixture['package']->id,
        ])
        ->assertRedirect();

    $round = EntityFeedbackRound::query()
        ->where('entity_id', $fixture['package']->id)
        ->latest('id')
        ->first();

    expect($round->notes()->count())->toBe(0)
        ->and($round->status)->toBe(FeedbackRoundStatus::OPEN);
})->group('first-note');

/**
 * Ruling on the change order ends the review: there is nothing left to negotiate,
 * so the round closes and the customer can no longer annotate it.
 */
it('closes the review when the customer decides the change order', function (): void {
    $fixture = feedbackFixture();
    $changeOrder = ChangeOrder::factory()->create([
        'project_id' => $fixture['project']->id,
        'status' => ChangeOrderStatus::CUSTOMER_ACTION_REQUIRED->value,
    ]);

    $round = EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::CHANGE_ORDER->value,
        'entity_id' => $changeOrder->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::RESOLVED->value,
        'opened_by' => $fixture['manager']->id,
        'submitted_by' => $fixture['customer']->id,
    ]);

    $item = ChangeOrderItem::factory()->create([
        'change_order_id' => $changeOrder->id,
        'description' => 'Replace the conduit run',
        'amount' => 500,
        'is_included' => true,
    ]);

    $this->actingAs($fixture['customer'])
        ->post(route('customer.change-orders.decide', [$fixture['project'], $changeOrder]), [
            'decisions' => [[
                'item_id' => $item->id,
                'decision' => 'approved',
            ]],
        ]);

    $round->refresh();
    $changeOrder->refresh();

    expect($changeOrder->status)->toBe(ChangeOrderStatus::APPROVED)
        ->and($round->status)->toBe(FeedbackRoundStatus::ACCEPTED)
        ->and($round->accepted_by)->toBe($fixture['customer']->id);
})->group('decided-closes-review');

it('refuses new customer feedback on a decided change order', function (): void {
    $fixture = feedbackFixture();
    $changeOrder = ChangeOrder::factory()->create([
        'project_id' => $fixture['project']->id,
        'status' => ChangeOrderStatus::APPROVED->value,
    ]);

    $round = EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::CHANGE_ORDER->value,
        'entity_id' => $changeOrder->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::OPEN->value,
        'opened_by' => $fixture['manager']->id,
    ]);

    // A leftover open round on a decided change order must not stay writable.
    expect($fixture['customer'])->not->toBeNull();

    $this->actingAs($fixture['customer'])
        ->post(route('entity-feedback.notes.store', $round), [
            'entity_type' => FeedbackEntityType::CHANGE_ORDER->value,
            'entity_id' => $changeOrder->id,
            'body' => 'One more thing',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($round->notes()->count())->toBe(0);
})->group('decided-closes-review');
