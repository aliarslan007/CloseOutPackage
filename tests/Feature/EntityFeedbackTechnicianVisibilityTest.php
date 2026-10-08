<?php

use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\EntityFeedback\Enums\FeedbackEntityType;
use App\Domains\EntityFeedback\Enums\FeedbackRoundStatus;
use App\Domains\EntityFeedback\Models\EntityFeedbackNote;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\EntityFeedback\Presenters\FeedbackRoundPresenter;
use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $market = Market::factory()->create();
    $this->manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $this->customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    $this->technician = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $this->project = Project::factory()->create([
        'market_id' => $market->id,
        'manager_id' => $this->manager->id,
    ]);
    $this->changeOrder = ChangeOrder::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->manager->id,
    ]);
});

/**
 * A submitted round assigned to the technician, every note on ONE photo so they
 * form a single thread: the client's first ask, a client follow-up, a manager
 * note, and the technician's own reply - in that creation order.
 */
function assignedRoundWithConversation(
    ChangeOrder $changeOrder,
    User $customer,
    User $manager,
    User $technician,
): EntityFeedbackRound {
    $round = EntityFeedbackRound::create([
        'entity_type' => FeedbackEntityType::CHANGE_ORDER->value,
        'entity_id' => $changeOrder->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::SUBMITTED->value,
        'opened_by' => $customer->id,
        'submitted_by' => $customer->id,
        'assigned_to_user_id' => $technician->id,
        'assigned_at' => now(),
    ]);

    $turns = [
        ['Blurry shot - please retake', $customer->id],
        ['Also redo the top corner', $customer->id],
        ["Retaking it on tomorrow's visit", $manager->id],
        ['Done, reshoot uploaded', $technician->id],
    ];

    foreach ($turns as [$body, $authorId]) {
        EntityFeedbackNote::create([
            'feedback_round_id' => $round->id,
            'entity_type' => FeedbackEntityType::CHANGE_ORDER->value,
            'entity_id' => $changeOrder->id,
            'source_photo_id' => 7,
            'source_photo_type' => 'photo',
            'original_image_url' => 'https://example.test/photo.jpg',
            'body' => $body,
            'created_by' => $authorId,
        ]);
    }

    return $round;
}

function presentedBodies(EntityFeedbackRound $round, User $viewer): array
{
    $notes = app(FeedbackRoundPresenter::class)->present($round, $viewer)['notes'] ?? [];

    return array_column($notes, 'body');
}

it('sends a technician only their work order, never the manager <-> customer thread', function (): void {
    $round = assignedRoundWithConversation($this->changeOrder, $this->customer, $this->manager, $this->technician);

    $bodies = presentedBodies($round, $this->technician);

    expect($bodies)
        ->toContain('Blurry shot - please retake')            // the client's first ask (root)
        ->toContain('Done, reshoot uploaded')                 // the technician's own reply
        ->not->toContain('Also redo the top corner')          // client follow-up: hidden
        ->not->toContain("Retaking it on tomorrow's visit");  // manager→customer: hidden
});

it('shows the manager the whole conversation', function (): void {
    $round = assignedRoundWithConversation($this->changeOrder, $this->customer, $this->manager, $this->technician);

    expect(presentedBodies($round, $this->manager))->toHaveCount(4);
});

it('sends the client notes oldest-first so the first message is the thread root', function (): void {
    // The technician trim and the client's thread grouping both need the payload
    // in creation order; the notes() relation deliberately carries no default order.
    $round = assignedRoundWithConversation($this->changeOrder, $this->customer, $this->manager, $this->technician);

    expect(presentedBodies($round, $this->manager)[0])
        ->toBe('Blurry shot - please retake');
});
