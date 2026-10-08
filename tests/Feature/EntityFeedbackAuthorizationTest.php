<?php

use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\ChangeOrders\Models\ChangeOrderRecipient;
use App\Domains\EntityFeedback\Enums\FeedbackEntityType;
use App\Domains\EntityFeedback\Enums\FeedbackRoundStatus;
use App\Domains\EntityFeedback\Models\EntityFeedbackNote;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\EntityFeedback\Notifications\CustomerFeedbackSubmittedNotification;
use App\Domains\EntityFeedback\Notifications\FeedbackForwardedToTechnicianNotification;
use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Storage::fake('private');
    Notification::fake();

    $market = Market::factory()->create();
    $this->manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $this->customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    $this->project = Project::factory()->create([
        'market_id' => $market->id,
        'manager_id' => $this->manager->id,
    ]);
    $this->changeOrder = ChangeOrder::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->manager->id,
    ]);
});

/** An open round owned by the customer, with one note on it. */
function openRoundWithNote(
    ChangeOrder $changeOrder,
    User $author,
    FeedbackRoundStatus $status = FeedbackRoundStatus::OPEN,
): EntityFeedbackRound {
    $round = EntityFeedbackRound::create([
        'entity_type' => FeedbackEntityType::CHANGE_ORDER->value,
        'entity_id' => $changeOrder->id,
        'round_number' => 1,
        'status' => $status->value,
        'opened_by' => $author->id,
        'submitted_by' => $status->value === FeedbackRoundStatus::OPEN->value ? null : $author->id,
    ]);

    $note = EntityFeedbackNote::create([
        'feedback_round_id' => $round->id,
        'entity_type' => FeedbackEntityType::CHANGE_ORDER->value,
        'entity_id' => $changeOrder->id,
        'original_image_url' => 'https://example.test/photo.jpg',
        'body' => 'This shot is blurry.',
        'created_by' => $author->id,
    ]);

    $round->setRelation('notes', collect([$note]));

    return $round;
}

it('stops a stranger from deleting someone elses feedback note', function (): void {
    $round = openRoundWithNote($this->changeOrder, $this->customer);
    $note = $round->notes->first();
    $stranger = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->actingAs($stranger)
        ->deleteJson(route('entity-feedback.notes.destroy', $note))
        ->assertForbidden();

    $this->assertDatabaseHas('entity_feedback_notes', ['id' => $note->id]);
});

it('stops a customer from deleting a note they did not write', function (): void {
    $round = openRoundWithNote($this->changeOrder, $this->customer);
    $note = $round->notes->first();
    $otherCustomer = User::factory()->create()->assignRole(Role::CUSTOMER->value);

    $this->actingAs($otherCustomer)
        ->deleteJson(route('entity-feedback.notes.destroy', $note))
        ->assertForbidden();

    $this->assertDatabaseHas('entity_feedback_notes', ['id' => $note->id]);
});

it('still lets the author and a manager delete a note on an open round', function (): void {
    $round = openRoundWithNote($this->changeOrder, $this->customer);
    $note = $round->notes->first();

    $this->actingAs($this->customer)
        ->deleteJson(route('entity-feedback.notes.destroy', $note))
        ->assertRedirect();

    $this->assertDatabaseMissing('entity_feedback_notes', ['id' => $note->id]);
});

it('stops a customer from resolving a note', function (): void {
    $round = openRoundWithNote($this->changeOrder, $this->customer, FeedbackRoundStatus::SUBMITTED);
    $note = $round->notes->first();

    $this->actingAs($this->customer)
        ->patchJson(route('entity-feedback.notes.resolve', $note), [
            'manager_response' => 'Marked resolved by the customer.',
        ])
        ->assertForbidden();

    $this->assertDatabaseHas('entity_feedback_notes', [
        'id' => $note->id,
        'manager_response' => null,
    ]);
});

it('lets a manager resolve a note', function (): void {
    $round = openRoundWithNote($this->changeOrder, $this->customer, FeedbackRoundStatus::SUBMITTED);
    $note = $round->notes->first();

    $this->actingAs($this->manager)
        ->patchJson(route('entity-feedback.notes.resolve', $note), [
            'manager_response' => 'Retaken and replaced.',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('entity_feedback_notes', [
        'id' => $note->id,
        'manager_response' => 'Retaken and replaced.',
    ]);
});

it('stops a customer from submitting or accepting a round they did not open', function (): void {
    $round = openRoundWithNote($this->changeOrder, $this->customer);
    $stranger = User::factory()->create()->assignRole(Role::CUSTOMER->value);

    $this->actingAs($stranger)
        ->postJson(route('entity-feedback.rounds.submit', $round))
        ->assertForbidden();

    $this->assertDatabaseHas('entity_feedback_rounds', [
        'id' => $round->id,
        'status' => FeedbackRoundStatus::OPEN->value,
    ]);
});

it('stops a customer from forwarding a round to a technician', function (): void {
    $round = openRoundWithNote($this->changeOrder, $this->customer);
    $technician = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    // The route middleware also blocks this, and the policy is the backstop.
    $this->actingAs($this->customer)
        ->postJson(route('entity-feedback.rounds.forward-technician', $round), [
            'assigned_to_user_id' => $technician->id,
        ])
        ->assertForbidden();
});

it('refuses a decision token issued for another change orders round', function (): void {
    $round = openRoundWithNote($this->changeOrder, $this->customer);

    $otherOrder = ChangeOrder::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->manager->id,
    ]);

    // The factory only stores a hash, so mint a known raw token and hash it
    // ourselves — otherwise there is no way to build the decision URL.
    $rawToken = ChangeOrderRecipient::generateToken();

    $recipient = ChangeOrderRecipient::factory()->create([
        'change_order_id' => $otherOrder->id,
        'user_id' => $this->customer->id,
        'token_hash' => ChangeOrderRecipient::hashToken($rawToken),
    ]);

    // The token is genuine and unexpired, but it belongs to a different change
    // order, so it must not be able to submit this round.
    $this->post(route('change-orders.token.feedback.submit', [
        'token' => $rawToken,
        'round' => $round->id,
    ]))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseHas('entity_feedback_rounds', [
        'id' => $round->id,
        'status' => FeedbackRoundStatus::OPEN->value,
    ]);
});

it('keeps the attachment validation on note resolution', function (): void {
    $round = openRoundWithNote($this->changeOrder, $this->customer, FeedbackRoundStatus::SUBMITTED);
    $note = $round->notes->first();

    $this->actingAs($this->manager)
        ->patchJson(route('entity-feedback.notes.resolve', $note), [
            'manager_response' => 'Here is the replacement.',
            'replacement_image' => UploadedFile::fake()->create('not-an-image.txt', 8),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('replacement_image');
});

it('gives the notification centre a title and a body to render', function (): void {
    $round = openRoundWithNote($this->changeOrder, $this->customer, FeedbackRoundStatus::SUBMITTED);

    // The notification centre reads data['title'] and data['body']. Before this
    // the payload carried only a type and a message, so the manager saw a bare
    // "Notification" with an empty description and could not tell what arrived.
    $payload = (new CustomerFeedbackSubmittedNotification(
        $round,
        $this->customer,
    ))->toArray($this->manager);

    expect($payload)->toHaveKeys(['title', 'body'])
        ->and($payload['title'])->toBe('New customer feedback')
        ->and($payload['body'])->toContain($this->customer->name)
        ->and($payload['body'])->toContain('Change Order');

    $technician = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $round->update(['assigned_to_user_id' => $technician->id]);

    $forwarded = (new FeedbackForwardedToTechnicianNotification(
        $round,
        $this->manager,
        'Retake the grounding shot.',
    ))->toArray($technician);

    expect($forwarded)->toHaveKeys(['title', 'body'])
        ->and($forwarded['title'])->toBe('Customer feedback assigned to you')
        ->and($forwarded['body'])->toContain($this->manager->name);

    $forwardedCustomer = (new FeedbackForwardedToTechnicianNotification(
        $round,
        $this->manager,
        'Retake the grounding shot.',
    ))->toArray($this->customer);

    expect($forwardedCustomer)->toHaveKeys(['title', 'body'])
        ->and($forwardedCustomer['title'])->toBe('Review assigned for field rework')
        ->and($forwardedCustomer['body'])->toContain($this->manager->name);
});

it('lets an admin delete a whole past review round', function (): void {
    $round = openRoundWithNote($this->changeOrder, $this->customer, FeedbackRoundStatus::SUBMITTED);
    $note = $round->notes->first();
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    $this->actingAs($admin)
        ->deleteJson(route('entity-feedback.rounds.delete', $round))
        ->assertRedirect();

    $this->assertDatabaseMissing('entity_feedback_rounds', ['id' => $round->id]);
    // The note goes with it via the DB's ON DELETE CASCADE, not app code.
    $this->assertDatabaseMissing('entity_feedback_notes', ['id' => $note->id]);
});

it('stops a manager who is not an admin from deleting a past review round', function (): void {
    $round = openRoundWithNote($this->changeOrder, $this->customer, FeedbackRoundStatus::SUBMITTED);

    $this->actingAs($this->manager)
        ->deleteJson(route('entity-feedback.rounds.delete', $round))
        ->assertForbidden();

    $this->assertDatabaseHas('entity_feedback_rounds', ['id' => $round->id]);
});
