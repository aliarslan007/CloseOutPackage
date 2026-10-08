<?php

use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\EntityFeedback\Enums\FeedbackEntityType;
use App\Domains\EntityFeedback\Enums\FeedbackRoundStatus;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\EntityFeedback\Presenters\FeedbackRoundPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

/**
 * Renders the three pages that serialize a feedback round. These were never
 * exercised by a test, which is how an undefined variable shipped a 500.
 */
it('renders the manager change order page with an open round', function (): void {
    $fixture = feedbackFixture();
    $changeOrder = ChangeOrder::factory()->create([
        'project_id' => $fixture['project']->id,
    ]);

    $round = EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::CHANGE_ORDER->value,
        'entity_id' => $changeOrder->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::OPEN->value,
        'opened_by' => $fixture['manager']->id,
    ]);

    $this->actingAs($fixture['manager'])
        ->get(route('projects.change-orders.show', [$fixture['project'], $changeOrder]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Projects/ChangeOrders/Show')
            ->has('feedbackRound')
        );
})->group('round-render');

it('renders the customer change order page with an open round', function (): void {
    $fixture = feedbackFixture();
    $changeOrder = ChangeOrder::factory()->create([
        'project_id' => $fixture['project']->id,
    ]);

    EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::CHANGE_ORDER->value,
        'entity_id' => $changeOrder->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::OPEN->value,
        'opened_by' => $fixture['manager']->id,
    ]);

    $this->actingAs($fixture['customer'])
        ->get(route('customer.change-orders.show', [$fixture['project'], $changeOrder]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Customer/ChangeOrders/Show')
            ->has('feedbackRound')
        );
})->group('round-render');

it('renders the close-out package pages with a round', function (): void {
    $fixture = feedbackFixture();
    $package = $fixture['package'];

    EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
        'entity_id' => $package->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::OPEN->value,
        'opened_by' => $fixture['manager']->id,
    ]);

    $this->actingAs($fixture['manager'])
        ->get(route('packages.show', $package))
        ->assertOk()
        // Depending on status this renders Packages/Show or Packages/Review;
        // both must carry the round without erroring.
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('feedback_round')
        );
})->group('round-render');

/**
 * The product rule: staff must not see the customer's draft. This asserts it at
 * the payload level, so it cannot be undone by a UI change.
 */
it('withholds the draft notes from a manager before submission', function (): void {
    $presenter = app(FeedbackRoundPresenter::class);
    $fixture = feedbackFixture();
    $package = $fixture['package'];

    $round = EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
        'entity_id' => $package->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::OPEN->value,
        'opened_by' => $fixture['manager']->id,
    ]);
    $round->notes()->create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
        'entity_id' => $package->id,
        'body' => 'SECRET DRAFT BODY',
        'created_by' => $fixture['customer']->id,
    ]);

    $managerView = $presenter->present($round->fresh(['notes.author']), $fixture['manager']);

    expect($managerView)->not->toBeNull()
        ->and($managerView['notes'])->toBe([])
        ->and($managerView[FeedbackRoundPresenter::FLAG_NOTES_WITHHELD])->toBeTrue()
        ->and(json_encode($managerView))->not->toContain('SECRET DRAFT BODY');
})->group('draft-privacy');

it('reveals the notes to the manager once the customer submits', function (): void {
    $presenter = app(FeedbackRoundPresenter::class);
    $fixture = feedbackFixture();
    $package = $fixture['package'];

    $round = EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
        'entity_id' => $package->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::SUBMITTED->value,
        'opened_by' => $fixture['manager']->id,
        'submitted_by' => $fixture['customer']->id,
        'submitted_at' => now(),
    ]);
    $round->notes()->create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
        'entity_id' => $package->id,
        'body' => 'The antenna is half out of frame.',
        'created_by' => $fixture['customer']->id,
    ]);

    $managerView = $presenter->present($round->fresh(['notes.author']), $fixture['manager']);

    expect($managerView['notes'])->toHaveCount(1)
        ->and($managerView)->not->toHaveKey(FeedbackRoundPresenter::FLAG_NOTES_WITHHELD)
        ->and($managerView['notes'][0]['body'])->toBe('The antenna is half out of frame.');
})->group('draft-privacy');

it('always shows the customer their own draft', function (): void {
    $presenter = app(FeedbackRoundPresenter::class);
    $fixture = feedbackFixture();
    $package = $fixture['package'];

    $round = EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
        'entity_id' => $package->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::OPEN->value,
        'opened_by' => $fixture['manager']->id,
    ]);
    $round->notes()->create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
        'entity_id' => $package->id,
        'body' => 'Still typing…',
        'created_by' => $fixture['customer']->id,
    ]);

    $customerView = $presenter->present($round->fresh(['notes.author']), $fixture['customer']);

    expect($customerView['notes'])->toHaveCount(1)
        ->and($customerView)->not->toHaveKey(FeedbackRoundPresenter::FLAG_NOTES_WITHHELD);
})->group('draft-privacy');

it('exposes the round to the portal under the key the client reads', function (): void {
    $fixture = feedbackFixture();
    $package = $fixture['package'];

    EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
        'entity_id' => $package->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::OPEN->value,
        'opened_by' => $fixture['manager']->id,
    ]);

    $this->actingAs($fixture['customer'])
        ->get(route('portal.projects.show', $fixture['project']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Customer/Portal/Projects/Show')
            ->where('project.packages.0.feedback_round.round_number', 1)
            ->missing('project.packages.0.active_feedback_round')
        );
})->group('draft-privacy');

/**
 * A manager answers two of five notes and has not pressed "Send Resolution to
 * Customer". That is work in progress and the customer must not see it: they
 * would learn the manager is mid-way through, note by note.
 */
it('withholds the manager draft responses from the customer until they are sent', function (): void {
    $presenter = app(FeedbackRoundPresenter::class);
    $fixture = feedbackFixture();
    $package = $fixture['package'];

    $round = EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
        'entity_id' => $package->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::SUBMITTED->value,
        'opened_by' => $fixture['manager']->id,
        'submitted_by' => $fixture['customer']->id,
        'submitted_at' => now(),
    ]);

    $answered = $round->notes()->create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
        'entity_id' => $package->id,
        'body' => 'Question one',
        'created_by' => $fixture['customer']->id,
    ]);
    $answered->update([
        'manager_response' => 'DRAFT ANSWER ONE',
        'resolved_at' => now(),
        'resolved_by' => $fixture['manager']->id,
        // No response_sent_at: this answer is a draft, not published.
    ]);

    $round->notes()->create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
        'entity_id' => $package->id,
        'body' => 'Question two',
        'created_by' => $fixture['customer']->id,
    ]);

    $round->refresh()->load('notes.author');

    // The manager keeps their own progress.
    $managerView = $presenter->present($round, $fixture['manager']);
    expect($managerView['notes'][0]['manager_response'])->toBe('DRAFT ANSWER ONE')
        ->and($managerView['notes'][0]['is_resolved'])->toBeTrue()
        ->and($managerView['notes'][1]['is_resolved'])->toBeFalse();

    // The customer sees the questions and nothing about the answers.
    $customerView = $presenter->present($round, $fixture['customer']);
    expect($customerView['notes'])->toHaveCount(2)
        ->and($customerView['notes'][0])->not->toHaveKey('manager_response')
        ->and($customerView['notes'][0]['is_resolved'])->toBeFalse()
        ->and($customerView['notes'][1]['is_resolved'])->toBeFalse()
        ->and(json_encode($customerView))->not->toContain('DRAFT ANSWER ONE');
})->group('draft-resolution-privacy');

it('reveals the resolutions to the customer once the round is sent', function (): void {
    $presenter = app(FeedbackRoundPresenter::class);
    $fixture = feedbackFixture();
    $package = $fixture['package'];

    $round = EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
        'entity_id' => $package->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::RESOLVED->value,
        'opened_by' => $fixture['manager']->id,
        'submitted_by' => $fixture['customer']->id,
        'submitted_at' => now(),
        'resolved_at' => now(),
        'resolved_by' => $fixture['manager']->id,
    ]);

    $note = $round->notes()->create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
        'entity_id' => $package->id,
        'body' => 'Question one',
        'created_by' => $fixture['customer']->id,
        'manager_response' => 'SENT ANSWER',
        'resolved_at' => now(),
        'resolved_by' => $fixture['manager']->id,
        // Stamped by the send action, which is what publishes an answer.
        'response_sent_at' => now(),
    ]);

    $customerView = $presenter->present($round->fresh()->load('notes.author'), $fixture['customer']);

    expect($customerView['notes'][0]['manager_response'])->toBe('SENT ANSWER')
        ->and($customerView['notes'][0]['is_resolved'])->toBeTrue();
})->group('draft-resolution-privacy');

/**
 * Once a round is accepted the customer must be able to open the next one. The
 * page used to hand the client the latest round whatever its status, so the
 * annotate flow posted to an accepted round and the note was refused.
 */
it('offers no active round once the previous one is accepted', function (): void {
    $fixture = feedbackFixture();
    $changeOrder = ChangeOrder::factory()->create([
        'project_id' => $fixture['project']->id,
    ]);

    EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::CHANGE_ORDER->value,
        'entity_id' => $changeOrder->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::ACCEPTED->value,
        'opened_by' => $fixture['manager']->id,
        'submitted_by' => $fixture['customer']->id,
        'accepted_at' => now(),
        'accepted_by' => $fixture['customer']->id,
    ]);

    // The controller hands over the *active* round, which no longer exists.
    expect($changeOrder->latestActiveFeedbackRound())->toBeNull()
        ->and($changeOrder->latestFeedbackRound())->not->toBeNull();
})->group('second-round');

/**
 * An approved change order has no live round, but the customer must still be
 * able to read what was discussed on each photo in every round.
 */
it('hands the customer the full round history once the review is closed', function (): void {
    $presenter = app(FeedbackRoundPresenter::class);
    $fixture = feedbackFixture();
    $changeOrder = ChangeOrder::factory()->create([
        'project_id' => $fixture['project']->id,
        'status' => 'approved',
    ]);

    foreach ([1, 2] as $n) {
        $round = EntityFeedbackRound::query()->create([
            'entity_type' => FeedbackEntityType::CHANGE_ORDER->value,
            'entity_id' => $changeOrder->id,
            'round_number' => $n,
            'status' => FeedbackRoundStatus::ACCEPTED->value,
            'opened_by' => $fixture['manager']->id,
            'submitted_by' => $fixture['customer']->id,
            'accepted_at' => now(),
            'accepted_by' => $fixture['customer']->id,
        ]);

        $round->notes()->create([
            'entity_type' => FeedbackEntityType::CHANGE_ORDER->value,
            'entity_id' => $changeOrder->id,
            'source_photo_id' => 100 + $n,
            'source_photo_type' => 'photo',
            'body' => "Round {$n} query",
            'created_by' => $fixture['customer']->id,
            'manager_response' => "Round {$n} answer",
            'resolved_at' => now(),
            'resolved_by' => $fixture['manager']->id,
            'response_sent_at' => now(),
        ]);
    }

    // No live round: the section falls back to the archive.
    expect($changeOrder->latestActiveFeedbackRound())->toBeNull();

    $history = $presenter->presentAll($changeOrder->feedbackRoundHistory(), $fixture['customer']);

    expect($history)->toHaveCount(2)
        ->and($history[0]['round_number'])->toBe(1)
        ->and($history[1]['round_number'])->toBe(2)
        // The published answers are part of the record and stay readable.
        ->and($history[0]['notes'][0]['manager_response'])->toBe('Round 1 answer')
        ->and($history[1]['notes'][0]['manager_response'])->toBe('Round 2 answer')
        ->and($history[0]['notes'][0]['source_photo_id'])->toBe(101);
})->group('round-history');
