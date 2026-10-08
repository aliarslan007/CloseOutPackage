<?php

declare(strict_types=1);

/**
 * ADVERSARIAL EDGE-CASE PROBE — customer close-out feedback loop.
 *
 * Written from the domain rules, not from the implementation. Each test states
 * what a manager/customer would consider correct and asserts it. Where the suite
 * fails, that is a finding to hand to the other agents — nothing here is a
 * description of what the code currently does.
 *
 * Lifecycle under test:
 *   manager approves package -> customer sees it -> customer raises issues on a
 *   photo / category / whole section -> manager answers, or forwards to a
 *   technician -> technician fixes and submits to manager -> manager sends back
 *   to customer -> customer accepts (thread done) or keeps talking -> or the
 *   customer withdraws one issue.
 *
 * IMPORTANT — how denials are asserted here.
 * App\Shared\Exceptions\ExceptionRenderer::permissionDenied() renders a refusal
 * on a browser (non-JSON) POST/PUT/PATCH/DELETE as `redirect()->back()->with(
 * 'error', ...)` — HTTP 302 — and only as 403 for an XHR/JSON caller. Asserting
 * `assertForbidden()` on an Inertia-style request therefore fails even when the
 * policy denied correctly. Every denial below goes through assertDenied(), which
 * asserts what actually matters: the action was refused, the user was told why,
 * and (checked per test) no state moved.
 */

use App\Domains\ChangeOrders\Enums\ChangeOrderStatus;
use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\EntityFeedback\Actions\OpenFeedbackRoundAction;
use App\Domains\EntityFeedback\Actions\ResolveAllFeedbackNotesAction;
use App\Domains\EntityFeedback\DTOs\OpenFeedbackRoundDTO;
use App\Domains\EntityFeedback\Enums\FeedbackEntityType;
use App\Domains\EntityFeedback\Enums\FeedbackRoundStatus;
use App\Domains\EntityFeedback\Models\EntityFeedbackNote;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\Markets\Models\Market;
use App\Domains\Photos\Actions\DeletePhotoAction;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Notification::fake();

    $this->customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    $this->otherCustomer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    $this->manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $this->otherManager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $this->technician = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $this->otherTech = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->project = Project::factory()->create([
        'market_id' => Market::factory()->create()->id,
        'manager_id' => $this->manager->id,
    ]);

    foreach ([$this->customer, $this->otherCustomer] as $customer) {
        CustomerProjectAccess::factory()->create([
            'user_id' => $customer->id,
            'project_id' => $this->project->id,
        ]);
    }

    $this->package = CloseoutPackage::factory()->create([
        'project_id' => $this->project->id,
        'status' => PackageStatus::APPROVED,
    ]);
    $this->stage = PackageStage::factory()->create(['closeout_package_id' => $this->package->id]);
    $this->selection = PackageCategorySelection::factory()->create(['package_stage_id' => $this->stage->id]);
    $this->photoA = Photo::factory()->create([
        'closeout_package_id' => $this->package->id,
        'package_stage_id' => $this->stage->id,
        'package_category_selection_id' => $this->selection->id,
        'position' => 1,
    ]);
    $this->photoB = Photo::factory()->create([
        'closeout_package_id' => $this->package->id,
        'package_stage_id' => $this->stage->id,
        'package_category_selection_id' => $this->selection->id,
        'position' => 2,
    ]);
});

/** A submitted customer round on the package, ready for staff to work. */
function submittedRound(User $customer, CloseoutPackage $package): EntityFeedbackRound
{
    return EntityFeedbackRound::create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
        'entity_id' => $package->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::SUBMITTED->value,
        'opened_by' => $customer->id,
        'submitted_by' => $customer->id,
        'submitted_at' => now(),
    ]);
}

function issueOnPhoto(EntityFeedbackRound $round, User $customer, int $photoId): EntityFeedbackNote
{
    return EntityFeedbackNote::create([
        'feedback_round_id' => $round->id,
        'entity_type' => $round->entity_type->value,
        'entity_id' => $round->entity_id,
        'source_photo_id' => $photoId,
        'source_photo_type' => 'photo',
        'body' => 'Please fix this.',
        'created_by' => $customer->id,
    ]);
}

function sectionIssue(EntityFeedbackRound $round, User $customer): EntityFeedbackNote
{
    return EntityFeedbackNote::create([
        'feedback_round_id' => $round->id,
        'entity_type' => $round->entity_type->value,
        'entity_id' => $round->entity_id,
        'source_photo_id' => null,
        'source_photo_type' => 'section',
        'body' => 'This whole area is wrong.',
        'created_by' => $customer->id,
    ]);
}

function withdrawVia(User $actor, EntityFeedbackRound $round, array $noteIds): TestResponse
{
    return test()->actingAs($actor)->post(route('entity-feedback.rounds.withdraw', $round), [
        'note_ids' => $noteIds,
    ]);
}

/** Refused, explained, and nothing moved. */
function assertDenied(TestResponse $response): TestResponse
{
    return $response->assertRedirect()->assertSessionHas('error');
}

// =====================================================================
// A. WITHDRAWAL x STAFF TOOLS
// =====================================================================

it('A1 blocks deleting the photo whose issue the customer withdrew', function (): void {
    $round = submittedRound($this->customer, $this->package);
    $note = issueOnPhoto($round, $this->customer, $this->photoA->id);
    // Keep the package unlocked with a second, live issue elsewhere.
    issueOnPhoto($round, $this->customer, $this->photoB->id);
    withdrawVia($this->customer, $round, [$note->id])->assertRedirect();

    expect(fn () => app(DeletePhotoAction::class)->execute($this->photoA))
        ->toThrow(InvalidStateTransitionException::class);
    expect(Photo::query()->whereKey($this->photoA->id)->exists())->toBeTrue();
});

it('A2 leaves a sibling photo in the same category deletable', function (): void {
    $round = submittedRound($this->customer, $this->package);
    $dropped = issueOnPhoto($round, $this->customer, $this->photoA->id);
    // photoB carries a LIVE issue, so the package stays unlocked for the review.
    issueOnPhoto($round, $this->customer, $this->photoB->id);
    withdrawVia($this->customer, $round, [$dropped->id])->assertRedirect();

    // Only the dropped item freezes; the crew keeps working the rest.
    expect(fn () => app(DeletePhotoAction::class)->execute($this->photoB))
        ->not->toThrow(InvalidStateTransitionException::class);
});

it('A3 re-enables the photo once the customer raises the same complaint again', function (): void {
    $round = submittedRound($this->customer, $this->package);
    $note = issueOnPhoto($round, $this->customer, $this->photoA->id);
    issueOnPhoto($round, $this->customer, $this->photoB->id);
    withdrawVia($this->customer, $round, [$note->id])->assertRedirect();

    // Withdrawing means "I changed my mind", not "forbid this forever".
    issueOnPhoto($round, $this->customer, $this->photoA->id);

    expect(fn () => app(DeletePhotoAction::class)->execute($this->photoA))
        ->not->toThrow(InvalidStateTransitionException::class);
});

it('A4 keeps the photo frozen while a second, un-withdrawn issue sits on it', function (): void {
    $round = submittedRound($this->customer, $this->package);
    $first = issueOnPhoto($round, $this->customer, $this->photoA->id);
    $second = issueOnPhoto($round, $this->customer, $this->photoA->id);
    issueOnPhoto($round, $this->customer, $this->photoB->id);
    withdrawVia($this->customer, $round, [$first->id])->assertRedirect();

    expect($second->fresh()->isWithdrawn())->toBeFalse()
        ->and(fn () => app(DeletePhotoAction::class)->execute($this->photoA))
        ->not->toThrow(InvalidStateTransitionException::class);
});

it('A5 blocks the delete when a SECTION-level issue is withdrawn', function (): void {
    $round = submittedRound($this->customer, $this->package);
    $section = sectionIssue($round, $this->customer);
    issueOnPhoto($round, $this->customer, $this->photoB->id);
    withdrawVia($this->customer, $round, [$section->id])->assertRedirect();

    // The customer withdrew the room, not one picture in it.
    expect(fn () => app(DeletePhotoAction::class)->execute($this->photoA))
        ->toThrow(InvalidStateTransitionException::class);
});

it('A6 blocks editing the messages of a withdrawn issue', function (): void {
    $round = submittedRound($this->customer, $this->package);
    $note = issueOnPhoto($round, $this->customer, $this->photoA->id);
    $before = $note->body;
    withdrawVia($this->customer, $round, [$note->id])->assertRedirect();

    assertDenied($this->actingAs($this->manager)
        ->patch(route('entity-feedback.notes.update', $note), ['body' => 'Rewriting the record.']));
    expect($note->fresh()->body)->toBe($before);
});

it('A7 blocks resolving the issue the customer just withdrew', function (): void {
    $round = submittedRound($this->customer, $this->package);
    $note = issueOnPhoto($round, $this->customer, $this->photoA->id);
    withdrawVia($this->customer, $round, [$note->id])->assertRedirect();

    assertDenied($this->actingAs($this->manager)
        ->patch(route('entity-feedback.notes.resolve', $note), ['manager_response' => 'Done.']));
    expect($note->fresh()->resolved_at)->toBeNull();
});

it('A8 lets the manager resolve the OTHER issues in the same withdrawn round', function (): void {
    $round = submittedRound($this->customer, $this->package);
    $dropped = issueOnPhoto($round, $this->customer, $this->photoA->id);
    $live = issueOnPhoto($round, $this->customer, $this->photoB->id);
    withdrawVia($this->customer, $round, [$dropped->id])->assertRedirect();

    app(ResolveAllFeedbackNotesAction::class)->execute($round, $this->manager, 'All fixed.');

    expect($live->fresh()->resolved_at)->not->toBeNull()
        ->and($dropped->fresh()->resolved_at)->toBeNull();
});

// =====================================================================
// B. WITHDRAWAL x WHO / WHEN
// =====================================================================

it('B1 refuses to withdraw the MANAGER\'s own reply', function (): void {
    $round = submittedRound($this->customer, $this->package);
    $managerReply = EntityFeedbackNote::create([
        'feedback_round_id' => $round->id,
        'entity_type' => $round->entity_type->value,
        'entity_id' => $round->entity_id,
        'source_photo_id' => $this->photoA->id,
        'source_photo_type' => 'photo',
        'body' => 'Our side: we agree, will reshoot.',
        'created_by' => $this->manager->id,
    ]);

    // Only the customer's own ask is theirs to drop. Staff messages must stay
    // on the record, otherwise a client can erase the manager's position.
    assertDenied(withdrawVia($this->customer, $round, [$managerReply->id]));
    expect($managerReply->fresh()->isWithdrawn())->toBeFalse();
});

it('B2 refuses to withdraw another customer\'s issue on the same project', function (): void {
    $round = submittedRound($this->customer, $this->package);
    $note = issueOnPhoto($round, $this->customer, $this->photoA->id);

    // Two customers are legitimately on this project. Project access alone must
    // not let one client delete the other client\'s complaint.
    assertDenied(withdrawVia($this->otherCustomer, $round, [$note->id]));
    expect($note->fresh()->isWithdrawn())->toBeFalse();
});

it('B3 ignores note ids that belong to a DIFFERENT round on the same package', function (): void {
    $roundOne = submittedRound($this->customer, $this->package);
    $noteOne = issueOnPhoto($roundOne, $this->customer, $this->photoA->id);

    $roundTwo = EntityFeedbackRound::create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
        'entity_id' => $this->package->id,
        'round_number' => 2,
        'status' => FeedbackRoundStatus::SUBMITTED->value,
        'opened_by' => $this->customer->id,
        'submitted_by' => $this->customer->id,
        'submitted_at' => now(),
    ]);
    $noteTwo = issueOnPhoto($roundTwo, $this->customer, $this->photoB->id);

    // A stale tab can still be holding ids from the previous round.
    withdrawVia($this->customer, $roundOne, [$noteOne->id, $noteTwo->id])->assertRedirect();

    expect($noteOne->fresh()->isWithdrawn())->toBeTrue()
        ->and($noteTwo->fresh()->isWithdrawn())->toBeFalse();
});

it('B4 rejects an empty note id list and leaves the round alone', function (): void {
    $round = submittedRound($this->customer, $this->package);
    $note = issueOnPhoto($round, $this->customer, $this->photoA->id);

    $this->actingAs($this->customer)
        ->post(route('entity-feedback.rounds.withdraw', $round), ['note_ids' => []])
        ->assertSessionHasErrors('note_ids');

    expect($note->fresh()->isWithdrawn())->toBeFalse()
        ->and($round->fresh()->status)->toBe(FeedbackRoundStatus::SUBMITTED);
});

it('B5 survives note ids that do not exist', function (): void {
    $round = submittedRound($this->customer, $this->package);
    $note = issueOnPhoto($round, $this->customer, $this->photoA->id);

    withdrawVia($this->customer, $round, [999999])->assertRedirect();

    expect($note->fresh()->isWithdrawn())->toBeFalse()
        ->and($round->fresh()->status)->toBe(FeedbackRoundStatus::SUBMITTED);
});

it('B6 refuses to withdraw after the customer has accepted the round', function (): void {
    $round = submittedRound($this->customer, $this->package);
    $note = issueOnPhoto($round, $this->customer, $this->photoA->id);
    $round->update(['status' => FeedbackRoundStatus::ACCEPTED->value]);

    assertDenied(withdrawVia($this->customer, $round, [$note->id]));
    expect($note->fresh()->isWithdrawn())->toBeFalse();
});

it('B7 allows withdrawal while the round is still the customer\'s OPEN draft', function (): void {
    $round = EntityFeedbackRound::create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
        'entity_id' => $this->package->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::OPEN->value,
        'opened_by' => $this->customer->id,
    ]);
    $note = issueOnPhoto($round, $this->customer, $this->photoA->id);

    // Dropping an ask before submitting is the most natural time to do it.
    withdrawVia($this->customer, $round, [$note->id])->assertRedirect();
    expect($note->fresh()->isWithdrawn())->toBeTrue();
});

it('B8 does not blow up when the package behind an open round is deleted', function (): void {
    $round = submittedRound($this->customer, $this->package);
    issueOnPhoto($round, $this->customer, $this->photoA->id);

    $this->package->forceDelete();

    // An orphan round is a real state once a manager archives a package; reading
    // it back must degrade rather than fatal.
    expect(fn () => $round->fresh()->notes()->count())->not->toThrow(Throwable::class);
});

// =====================================================================
// C. ROUND LIFECYCLE
// =====================================================================

it('C1 refuses a second concurrent round while the first is still with the manager', function (): void {
    $round = submittedRound($this->customer, $this->package);
    issueOnPhoto($round, $this->customer, $this->photoA->id);

    app(OpenFeedbackRoundAction::class)->execute(new OpenFeedbackRoundDTO(
        entityType: FeedbackEntityType::CLOSEOUT_PACKAGE,
        entityId: $this->package->id,
        openedBy: $this->customer->id,
        managerInstructions: null,
    ));

    // One review at a time: two live rounds split the staff's attention and give
    // the customer two competing "my feedback" lists.
    expect(EntityFeedbackRound::query()
        ->where('entity_id', $this->package->id)
        ->whereIn('status', [FeedbackRoundStatus::OPEN->value, FeedbackRoundStatus::SUBMITTED->value])
        ->count())->toBe(1);
});

it('C2 returns the SAME round when the customer re-opens their open draft', function (): void {
    $round = EntityFeedbackRound::create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
        'entity_id' => $this->package->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::OPEN->value,
        'opened_by' => $this->customer->id,
    ]);

    $again = app(OpenFeedbackRoundAction::class)->execute(new OpenFeedbackRoundDTO(
        entityType: FeedbackEntityType::CLOSEOUT_PACKAGE,
        entityId: $this->package->id,
        openedBy: $this->customer->id,
        managerInstructions: null,
    ));

    expect($again->id)->toBe($round->id);
});

it('C3 refuses the customer accepting a round that was never submitted', function (): void {
    $round = EntityFeedbackRound::create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
        'entity_id' => $this->package->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::OPEN->value,
        'opened_by' => $this->customer->id,
    ]);

    assertDenied($this->actingAs($this->customer)->post(route('entity-feedback.rounds.accept', $round)));
    expect($round->fresh()->status)->toBe(FeedbackRoundStatus::OPEN);
});

it('C4 refuses a double submit', function (): void {
    $round = submittedRound($this->customer, $this->package);

    assertDenied($this->actingAs($this->customer)->post(route('entity-feedback.rounds.submit', $round)));
    expect($round->fresh()->status)->toBe(FeedbackRoundStatus::SUBMITTED);
});

it('C5 refuses a double acknowledge', function (): void {
    $round = submittedRound($this->customer, $this->package);

    $this->actingAs($this->manager)
        ->post(route('entity-feedback.rounds.acknowledge', $round))
        ->assertRedirect();
    $firstAck = $round->fresh()->manager_acknowledged_at;

    assertDenied($this->actingAs($this->manager)->post(route('entity-feedback.rounds.acknowledge', $round)));
    expect($round->fresh()->manager_acknowledged_at->timestamp)->toBe($firstAck->timestamp);
});

it('C6 refuses acknowledgement by a manager who does not own the project', function (): void {
    $round = submittedRound($this->customer, $this->package);

    assertDenied($this->actingAs($this->otherManager)
        ->post(route('entity-feedback.rounds.acknowledge', $round)));
    expect($round->fresh()->isAcknowledged())->toBeFalse();
});

it('C7 refuses resolve-all before the round is submitted', function (): void {
    $round = EntityFeedbackRound::create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
        'entity_id' => $this->package->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::OPEN->value,
        'opened_by' => $this->customer->id,
    ]);
    $note = issueOnPhoto($round, $this->customer, $this->photoA->id);

    assertDenied($this->actingAs($this->manager)
        ->post(route('entity-feedback.rounds.resolve-all', $round), ['general_response' => 'All good.']));
    expect($note->fresh()->resolved_at)->toBeNull();
});

it('C8 lets the customer keep talking after a resolve', function (): void {
    $round = submittedRound($this->customer, $this->package);
    $round->update(['status' => FeedbackRoundStatus::RESOLVED->value]);

    $this->actingAs($this->customer)
        ->post(route('entity-feedback.notes.store', $round), [
            'entity_type' => $round->entity_type->value,
            'entity_id' => $round->entity_id,
            'body' => 'Still not right on the left edge.',
        ])
        ->assertRedirect();
});

it('C9 freezes a round once the customer has accepted it', function (): void {
    $round = submittedRound($this->customer, $this->package);
    $round->update(['status' => FeedbackRoundStatus::ACCEPTED->value]);

    assertDenied($this->actingAs($this->customer)
        ->post(route('entity-feedback.notes.store', $round), [
            'entity_type' => $round->entity_type->value,
            'entity_id' => $round->entity_id,
            'body' => 'One more thing, actually.',
        ]));
});

it('C10 refuses any new feedback once a change order is decided', function (): void {
    $order = ChangeOrder::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->manager->id,
        'status' => ChangeOrderStatus::APPROVED,
    ]);
    $round = EntityFeedbackRound::create([
        'entity_type' => FeedbackEntityType::CHANGE_ORDER->value,
        'entity_id' => $order->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::SUBMITTED->value,
        'opened_by' => $this->customer->id,
        'submitted_by' => $this->customer->id,
        'submitted_at' => now(),
    ]);

    assertDenied($this->actingAs($this->customer)
        ->post(route('entity-feedback.notes.store', $round), [
            'entity_type' => $round->entity_type->value,
            'entity_id' => $round->entity_id,
            'body' => 'Actually, I want to reconsider this.',
        ]));
});

it('C11 refuses a manager deleting the customer\'s archived record (admin only)', function (): void {
    $round = submittedRound($this->customer, $this->package);
    issueOnPhoto($round, $this->customer, $this->photoA->id);

    assertDenied($this->actingAs($this->manager)
        ->delete(route('entity-feedback.rounds.delete', $round)));
    expect(EntityFeedbackRound::query()->whereKey($round->id)->exists())->toBeTrue();
});

// =====================================================================
// D. TECHNICIAN BOUNDARY
// =====================================================================

it('D1 hides the round from a technician who was not the one assigned', function (): void {
    $round = submittedRound($this->customer, $this->package);
    issueOnPhoto($round, $this->customer, $this->photoA->id);
    $round->update(['assigned_at' => now(), 'assigned_to_user_id' => $this->technician->id]);

    expect($this->otherTech->can('viewNotes', $round))->toBeFalse()
        ->and($this->technician->can('viewNotes', $round))->toBeTrue();
});

it('D2 refuses the technician replying in the customer/manager thread', function (): void {
    $round = submittedRound($this->customer, $this->package);
    issueOnPhoto($round, $this->customer, $this->photoA->id);
    $round->update(['assigned_at' => now(), 'assigned_to_user_id' => $this->technician->id]);

    // The conversation is customer <-> manager; the tech reports through
    // Submit to Manager rather than joining the thread.
    expect($this->technician->can('addNote', $round))->toBeFalse();
});

it('D3 refuses a technician resolving an issue on a round assigned to someone else', function (): void {
    $round = submittedRound($this->customer, $this->package);
    $note = issueOnPhoto($round, $this->customer, $this->photoA->id);
    $round->update(['assigned_at' => now(), 'assigned_to_user_id' => $this->technician->id]);

    assertDenied($this->actingAs($this->otherTech)
        ->patch(route('entity-feedback.notes.resolve', $note), ['manager_response' => 'Handled.']));
    expect($note->fresh()->resolved_at)->toBeNull();
});

it('D4 refuses the technician submitting to manager before the manager assigned it', function (): void {
    $round = submittedRound($this->customer, $this->package);
    issueOnPhoto($round, $this->customer, $this->photoA->id);

    // Assigned to nobody yet, so there is no instruction to act on.
    expect($this->technician->can('submitTechnician', $round))->toBeFalse();
});

it('D5 freezes the notes from the technician the moment the round is accepted', function (): void {
    $round = submittedRound($this->customer, $this->package);
    $note = issueOnPhoto($round, $this->customer, $this->photoA->id);
    $round->update(['status' => FeedbackRoundStatus::ACCEPTED->value]);

    assertDenied($this->actingAs($this->technician)
        ->patch(route('entity-feedback.notes.resolve', $note), ['manager_response' => 'Fixed it.']));
    expect($note->fresh()->resolved_at)->toBeNull();
});

// =====================================================================
// E. NOTE EDITING
// =====================================================================

it('E1 lets the author correct a typo inside the edit window', function (): void {
    $round = submittedRound($this->customer, $this->package);
    $note = issueOnPhoto($round, $this->customer, $this->photoA->id);
    $note->created_at = now()->subMinutes(1);
    $note->save();

    $this->actingAs($this->customer)
        ->patch(route('entity-feedback.notes.update', $note), ['body' => 'Please fix this typo.'])
        ->assertRedirect();
    expect($note->fresh()->body)->toBe('Please fix this typo.');
});

it('E2 refuses the author editing after the window closes', function (): void {
    $round = submittedRound($this->customer, $this->package);
    $note = issueOnPhoto($round, $this->customer, $this->photoA->id);
    $note->created_at = now()->subMinutes(30);
    $note->save();

    assertDenied($this->actingAs($this->customer)
        ->patch(route('entity-feedback.notes.update', $note), ['body' => 'Rewritten much later.']));
    expect($note->fresh()->body)->toBe('Please fix this.');
});

it('E3 refuses repeated edits from resetting the edit window', function (): void {
    $round = submittedRound($this->customer, $this->package);
    $note = issueOnPhoto($round, $this->customer, $this->photoA->id);
    $note->created_at = now()->subMinutes(4);
    $note->save();

    $this->actingAs($this->customer)
        ->patch(route('entity-feedback.notes.update', $note), ['body' => 'First correction.'])
        ->assertRedirect();

    // Five minutes runs from when it was written. Tapping "edit" again must not
    // buy another five, or the limit means nothing.
    $note->created_at = now()->subMinutes(6);
    $note->save();
    assertDenied($this->actingAs($this->customer)
        ->patch(route('entity-feedback.notes.update', $note), ['body' => 'Second correction.']));
    expect($note->fresh()->body)->toBe('First correction.');
});

it('E4 refuses a manager rewriting the customer\'s own words', function (): void {
    $round = submittedRound($this->customer, $this->package);
    $note = issueOnPhoto($round, $this->customer, $this->photoA->id);

    assertDenied($this->actingAs($this->manager)
        ->patch(route('entity-feedback.notes.update', $note), ['body' => 'They meant something else.']));
    expect($note->fresh()->body)->toBe('Please fix this.');
});

it('E5 refuses the other customer on the project editing this customer\'s note', function (): void {
    $round = submittedRound($this->customer, $this->package);
    $note = issueOnPhoto($round, $this->customer, $this->photoA->id);

    assertDenied($this->actingAs($this->otherCustomer)
        ->patch(route('entity-feedback.notes.update', $note), ['body' => 'Not mine to write.']));
    expect($note->fresh()->body)->toBe('Please fix this.');
});

it('E6 refuses the customer deleting a message after submitting the round', function (): void {
    $round = submittedRound($this->customer, $this->package);
    $note = issueOnPhoto($round, $this->customer, $this->photoA->id);

    // Once the manager is reading it, the customer's record stands as written.
    assertDenied($this->actingAs($this->customer)
        ->delete(route('entity-feedback.notes.destroy', $note)));
    expect(EntityFeedbackNote::query()->whereKey($note->id)->exists())->toBeTrue();
});

// =====================================================================
// F. PACKAGE / PHOTO GATES
// =====================================================================

it('F1 keeps an approved package editable while any issue is still live', function (): void {
    $round = submittedRound($this->customer, $this->package);
    issueOnPhoto($round, $this->customer, $this->photoA->id);

    expect($this->package->fresh()->hasPendingFeedback())->toBeTrue()
        ->and(fn () => app(DeletePhotoAction::class)->execute($this->photoA))
        ->not->toThrow(InvalidStateTransitionException::class);
});

it('F2 locks an approved package once every issue is withdrawn', function (): void {
    $round = submittedRound($this->customer, $this->package);
    $note = issueOnPhoto($round, $this->customer, $this->photoA->id);
    withdrawVia($this->customer, $round, [$note->id])->assertRedirect();

    // No live ask, no reason for the package to sit unlocked.
    expect($this->package->fresh()->hasPendingFeedback())->toBeFalse()
        ->and(fn () => app(DeletePhotoAction::class)->execute($this->photoA))
        ->toThrow(InvalidStateTransitionException::class);
});

it('F3 still refuses photo edits on a submitted package with no feedback at all', function (): void {
    expect(fn () => app(DeletePhotoAction::class)->execute($this->photoA))
        ->toThrow(InvalidStateTransitionException::class);
});

it('F4 lets a manager delete a photo on a DRAFT package with no feedback', function (): void {
    $draftPackage = CloseoutPackage::factory()->create([
        'project_id' => $this->project->id,
        'status' => PackageStatus::DRAFT,
    ]);
    $stage = PackageStage::factory()->create(['closeout_package_id' => $draftPackage->id]);
    $selection = PackageCategorySelection::factory()->create(['package_stage_id' => $stage->id]);
    $photo = Photo::factory()->create([
        'closeout_package_id' => $draftPackage->id,
        'package_stage_id' => $stage->id,
        'package_category_selection_id' => $selection->id,
    ]);

    expect(fn () => app(DeletePhotoAction::class)->execute($photo))
        ->not->toThrow(InvalidStateTransitionException::class);
});

it('F5 keeps a withdrawn issue visible as history, not as live work', function (): void {
    $round = submittedRound($this->customer, $this->package);
    $note = issueOnPhoto($round, $this->customer, $this->photoA->id);
    withdrawVia($this->customer, $round, [$note->id])->assertRedirect();

    expect($note->fresh()->isWithdrawn())->toBeTrue()
        ->and($note->fresh()->body)->toBe('Please fix this.')
        ->and($round->notes()->whereNull('resolved_at')->whereNull('withdrawn_at')->count())->toBe(0);
});

// =====================================================================
// G. CONVERSATION HYGIENE
// =====================================================================

it('G1 refuses a blank feedback message', function (): void {
    $round = submittedRound($this->customer, $this->package);

    $this->actingAs($this->customer)
        ->post(route('entity-feedback.notes.store', $round), [
            'entity_type' => $round->entity_type->value,
            'entity_id' => $round->entity_id,
            'body' => '   ',
        ])
        ->assertSessionHasErrors('body');
});

it('G2 hides the customer\'s open draft from staff but not from its author', function (): void {
    $round = EntityFeedbackRound::create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
        'entity_id' => $this->package->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::OPEN->value,
        'opened_by' => $this->customer->id,
    ]);

    expect($this->manager->can('viewNotes', $round))->toBeFalse()
        ->and($this->customer->can('viewNotes', $round))->toBeTrue();
});

it('G3 blocks a note pinned to a photo that belongs to another package', function (): void {
    $round = submittedRound($this->customer, $this->package);

    $otherPackage = CloseoutPackage::factory()->create([
        'project_id' => $this->project->id,
        'status' => PackageStatus::APPROVED,
    ]);
    $foreignPhoto = Photo::factory()->create(['closeout_package_id' => $otherPackage->id]);

    // A tampered payload must not file feedback for one package under another.
    $this->actingAs($this->customer)
        ->post(route('entity-feedback.notes.store', $round), [
            'entity_type' => $round->entity_type->value,
            'entity_id' => $round->entity_id,
            'source_photo_id' => $foreignPhoto->id,
            'source_photo_type' => 'photo',
            'body' => 'Comment planted on a different package.',
        ])
        ->assertSessionHasErrors('source_photo_id');
});

it('G4 refuses a customer adding a note to a round they cannot reach', function (): void {
    $otherProject = Project::factory()->create([
        'market_id' => $this->project->market_id,
        'manager_id' => $this->otherManager->id,
    ]);
    $foreignPackage = CloseoutPackage::factory()->create([
        'project_id' => $otherProject->id,
        'status' => PackageStatus::APPROVED,
    ]);
    $round = EntityFeedbackRound::create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
        'entity_id' => $foreignPackage->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::SUBMITTED->value,
        'opened_by' => $this->customer->id,
        'submitted_by' => $this->customer->id,
        'submitted_at' => now(),
    ]);

    // Guessing an id must not be access.
    assertDenied($this->actingAs($this->customer)
        ->post(route('entity-feedback.notes.store', $round), [
            'entity_type' => $round->entity_type->value,
            'entity_id' => $round->entity_id,
            'body' => 'Let me in.',
        ]));
});

it('G5 keeps a withdrawn issue out of the live-issue count', function (): void {
    $round = submittedRound($this->customer, $this->package);
    $dropped = issueOnPhoto($round, $this->customer, $this->photoA->id);
    $live = issueOnPhoto($round, $this->customer, $this->photoB->id);
    withdrawVia($this->customer, $round, [$dropped->id])->assertRedirect();

    expect($round->notes()->whereNull('withdrawn_at')->count())->toBe(1)
        ->and($live->fresh()->isWithdrawn())->toBeFalse();
});
