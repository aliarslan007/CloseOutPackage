<?php

use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\EntityFeedback\Actions\AddFeedbackAnnotationNoteAction;
use App\Domains\EntityFeedback\Actions\ResolveAllFeedbackNotesAction;
use App\Domains\EntityFeedback\Actions\UpdateFeedbackNoteAction;
use App\Domains\EntityFeedback\DTOs\AddFeedbackAnnotationNoteDTO;
use App\Domains\EntityFeedback\Enums\FeedbackEntityType;
use App\Domains\EntityFeedback\Enums\FeedbackRoundStatus;
use App\Domains\EntityFeedback\Models\EntityFeedbackNote;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\EntityFeedback\Notifications\FeedbackIssueWithdrawnNotification;
use App\Domains\EntityFeedback\Support\AssertFeedbackTargetNotWithdrawn;
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
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Notification::fake();

    $this->customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    $this->manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $this->technician = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->project = Project::factory()->create([
        'market_id' => Market::factory()->create()->id,
        'manager_id' => $this->manager->id,
    ]);
    CustomerProjectAccess::factory()->create([
        'user_id' => $this->customer->id,
        'project_id' => $this->project->id,
    ]);

    $this->changeOrder = ChangeOrder::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->manager->id,
    ]);
});

/**
 * A submitted review carrying two separate issues - a conversation on photo 11
 * (ask plus a follow-up) and a single message on photo 22 - which is the shape
 * withdrawal has to respect: one issue off, the other untouched.
 */
function roundWithTwoIssues(User $customer, ChangeOrder $changeOrder): EntityFeedbackRound
{
    $round = EntityFeedbackRound::create([
        'entity_type' => FeedbackEntityType::CHANGE_ORDER->value,
        'entity_id' => $changeOrder->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::SUBMITTED->value,
        'opened_by' => $customer->id,
        'submitted_by' => $customer->id,
        'submitted_at' => now(),
    ]);

    foreach ([11, 11, 22] as $index => $photoId) {
        EntityFeedbackNote::create([
            'feedback_round_id' => $round->id,
            'entity_type' => FeedbackEntityType::CHANGE_ORDER->value,
            'entity_id' => $changeOrder->id,
            'source_photo_id' => $photoId,
            'source_photo_type' => 'photo',
            'original_image_url' => "https://example.test/photo-{$photoId}.jpg",
            'body' => 'Issue '.($index + 1).' on photo '.$photoId.', please fix.',
            'created_by' => $customer->id,
        ]);
    }

    return $round;
}

/**
 * A section-level issue - the customer's note about a whole area rather than a
 * picture - filed through the same action the app uses, so the null photo id
 * looks exactly like it does in real data.
 */
function addSectionIssue(EntityFeedbackRound $round, User $author, string $body): void
{
    app(AddFeedbackAnnotationNoteAction::class)->execute($round, new AddFeedbackAnnotationNoteDTO(
        entityType: $round->entity_type,
        entityId: $round->entity_id,
        originalImageUrl: 'https://example.test/section.jpg',
        body: $body,
        createdBy: $author->id,
    ));
}

function replyOn(EntityFeedbackRound $round, User $author, int $photoId, string $body): void
{
    app(AddFeedbackAnnotationNoteAction::class)->execute($round, new AddFeedbackAnnotationNoteDTO(
        entityType: FeedbackEntityType::CHANGE_ORDER,
        entityId: $round->entity_id,
        originalImageUrl: "https://example.test/photo-{$photoId}.jpg",
        body: $body,
        createdBy: $author->id,
        sourcePhotoId: $photoId,
        sourcePhotoType: 'photo',
    ));
}

it('withdraws the one issue the customer names and leaves the review alone', function (): void {
    $round = roundWithTwoIssues($this->customer, $this->changeOrder);
    [$issueOneA, $issueOneB, $issueTwo] = $round->notes()->orderBy('id')->get()->all();

    $this->actingAs($this->customer)
        ->postJson(route('entity-feedback.rounds.withdraw', $round), [
            'note_ids' => [$issueOneA->id, $issueOneB->id],
        ])
        ->assertRedirect();

    expect($issueOneA->fresh()->isWithdrawn())->toBeTrue()
        ->and($issueOneB->fresh()->isWithdrawn())->toBeTrue()
        ->and($issueOneB->fresh()->withdrawn_by)->toBe($this->customer->id)
        // The other conversation in the same review is still live work.
        ->and($issueTwo->fresh()->isWithdrawn())->toBeFalse()
        // Withdrawing an issue is not answering or closing the review.
        ->and($round->fresh()->status)->toBe(FeedbackRoundStatus::SUBMITTED);
});

it('reaches the client through the serialized note', function (): void {
    $round = roundWithTwoIssues($this->customer, $this->changeOrder);
    $note = $round->notes()->orderBy('id')->first();

    $note->update(['withdrawn_at' => now(), 'withdrawn_by' => $this->customer->id]);

    expect($note->fresh()->toArray()['is_withdrawn'])->toBeTrue();
});

it('refuses withdrawal by staff, who may resolve an issue but not drop it', function (): void {
    $round = roundWithTwoIssues($this->customer, $this->changeOrder);
    $note = $round->notes()->orderBy('id')->first();

    foreach ([$this->manager, $this->technician] as $staff) {
        $this->actingAs($staff)
            ->postJson(route('entity-feedback.rounds.withdraw', $round), ['note_ids' => [$note->id]])
            ->assertForbidden();
    }

    expect($note->fresh()->isWithdrawn())->toBeFalse();
});

it('refuses a customer who has no access to the project behind the round', function (): void {
    $round = roundWithTwoIssues($this->customer, $this->changeOrder);
    $note = $round->notes()->orderBy('id')->first();
    $stranger = User::factory()->create()->assignRole(Role::CUSTOMER->value);

    $this->actingAs($stranger)
        ->postJson(route('entity-feedback.rounds.withdraw', $round), ['note_ids' => [$note->id]])
        ->assertForbidden();

    expect($note->fresh()->isWithdrawn())->toBeFalse();
});

it('refuses a withdrawal once the review has been accepted', function (): void {
    $round = roundWithTwoIssues($this->customer, $this->changeOrder);
    $round->update(['status' => FeedbackRoundStatus::ACCEPTED->value]);
    $note = $round->notes()->orderBy('id')->first();

    $this->actingAs($this->customer)
        ->postJson(route('entity-feedback.rounds.withdraw', $round), ['note_ids' => [$note->id]])
        ->assertForbidden();

    expect($note->fresh()->isWithdrawn())->toBeFalse();
});

it('refuses the customer their own reply into a withdrawn issue, the conversation being closed', function (): void {
    $round = roundWithTwoIssues($this->customer, $this->changeOrder);
    $withdrawn = $round->notes()->where('source_photo_id', 11)->first();
    $withdrawn->update(['withdrawn_at' => now(), 'withdrawn_by' => $this->customer->id]);

    expect(fn () => replyOn($round, $this->customer, 11, 'Actually, I do want this one looked at.'))
        ->toThrow(InvalidStateTransitionException::class)
        // Nothing was filed on the dropped conversation, and it stays withdrawn.
        ->and($round->notes()->where('source_photo_id', 11)->count())->toBe(2)
        ->and($withdrawn->fresh()->isWithdrawn())->toBeTrue();
});

it('refuses a staff reply into a withdrawn issue, since there is nothing left to answer', function (): void {
    $round = roundWithTwoIssues($this->customer, $this->changeOrder);
    $note = $round->notes()->where('source_photo_id', 11)->first();
    $note->update(['withdrawn_at' => now(), 'withdrawn_by' => $this->customer->id]);

    expect(fn () => replyOn($round, $this->manager, 11, 'Noted - we will leave it alone.'))
        ->toThrow(InvalidStateTransitionException::class)
        // No staff message was filed on the dropped conversation.
        ->and($round->notes()->where('source_photo_id', 11)->count())->toBe(2);
});

it('leaves a withdrawn issue alone when the manager resolves the rest of the review', function (): void {
    $round = roundWithTwoIssues($this->customer, $this->changeOrder);
    $withdrawn = $round->notes()->where('source_photo_id', 11)->first();
    $withdrawn->update(['withdrawn_at' => now(), 'withdrawn_by' => $this->customer->id]);
    $live = $round->notes()->where('source_photo_id', 22)->first();

    app(ResolveAllFeedbackNotesAction::class)->execute($round, $this->manager, 'All fixed.');

    // The live issue is answered; the withdrawn one keeps its own label.
    expect($live->fresh()->resolved_at)->not->toBeNull()
        ->and($withdrawn->fresh()->resolved_at)->toBeNull()
        ->and($withdrawn->fresh()->isWithdrawn())->toBeTrue();
});

it('closes a withdrawn conversation to a reply that arrives without a thread type', function (): void {
    $round = roundWithTwoIssues($this->customer, $this->changeOrder);
    $note = $round->notes()->where('source_photo_id', 11)->first();
    $note->update(['withdrawn_at' => now(), 'withdrawn_by' => $this->customer->id]);

    // A message pinned to a photo but typed by nothing is the same conversation
    // (build-feedback-threads.ts agrees), so it is closed the same way.
    expect(fn () => app(AddFeedbackAnnotationNoteAction::class)->execute($round, new AddFeedbackAnnotationNoteDTO(
        entityType: FeedbackEntityType::CHANGE_ORDER,
        entityId: $round->entity_id,
        originalImageUrl: 'https://example.test/photo-11.jpg',
        body: 'Still want this one fixed.',
        createdBy: $this->customer->id,
        sourcePhotoId: 11,
    )))->toThrow(InvalidStateTransitionException::class)
        ->and($note->fresh()->isWithdrawn())->toBeTrue();
});

it('closes only the withdrawn conversation, leaving every other issue writable', function (): void {
    $round = roundWithTwoIssues($this->customer, $this->changeOrder);
    addSectionIssue($round, $this->customer, 'The whole room needs a repaint.');
    $photoIssue = $round->notes()->where('source_photo_id', 11)->first();
    $sectionIssue = $round->notes()->whereNull('source_photo_id')->first();
    $photoIssue->update(['withdrawn_at' => now(), 'withdrawn_by' => $this->customer->id]);
    $sectionIssue->update(['withdrawn_at' => now(), 'withdrawn_by' => $this->customer->id]);

    // The other photo was never dropped, so it stays open for the customer.
    replyOn($round, $this->customer, 22, 'This one is still a problem.');

    // A section message carries no photo either, so matching on that alone would
    // file it into the closed section conversation instead.
    expect(fn () => addSectionIssue($round, $this->customer, 'On second thought, repaint it.'))
        ->toThrow(InvalidStateTransitionException::class)
        ->and($round->notes()->where('source_photo_id', 22)->count())->toBe(2)
        ->and($photoIssue->fresh()->isWithdrawn())->toBeTrue()
        ->and($sectionIssue->fresh()->isWithdrawn())->toBeTrue();
});

it('notifies the assigned manager and technician when an issue is withdrawn', function (): void {
    $round = roundWithTwoIssues($this->customer, $this->changeOrder);
    $round->update(['assigned_to_user_id' => $this->technician->id]);
    $note = $round->notes()->where('source_photo_id', 11)->first();

    $this->actingAs($this->customer)
        ->postJson(route('entity-feedback.rounds.withdraw', $round), ['note_ids' => [$note->id]])
        ->assertRedirect();

    // Both stop work on the dropped issue, so both must hear about it.
    Notification::assertSentTo($this->manager, FeedbackIssueWithdrawnNotification::class);
    Notification::assertSentTo($this->technician, FeedbackIssueWithdrawnNotification::class);
});

it('sends no withdrawal notification when nothing was newly withdrawn', function (): void {
    $round = roundWithTwoIssues($this->customer, $this->changeOrder);
    $note = $round->notes()->where('source_photo_id', 11)->first();
    $note->update(['withdrawn_at' => now(), 'withdrawn_by' => $this->customer->id]);

    $this->actingAs($this->customer)
        ->postJson(route('entity-feedback.rounds.withdraw', $round), ['note_ids' => [$note->id]])
        ->assertRedirect();

    // Re-withdrawing an already-dropped issue is not a state change to announce.
    Notification::assertNothingSentTo($this->manager);
});

it('freezes the messages of a withdrawn issue, so a closed conversation is never rewritten', function (): void {
    $round = roundWithTwoIssues($this->customer, $this->changeOrder);
    $note = $round->notes()->where('source_photo_id', 11)->first();
    $before = $note->body;
    $note->update(['withdrawn_at' => now(), 'withdrawn_by' => $this->customer->id]);

    // Even its author, still inside the edit window.
    expect(fn () => app(UpdateFeedbackNoteAction::class)->execute($note, $this->customer->id, 'Edited after withdrawing.'))
        ->toThrow(InvalidStateTransitionException::class)
        ->and($note->fresh()->body)->toBe($before);
});

it('restores the package lock once the customer withdraws every issue', function (): void {
    $package = CloseoutPackage::factory()->create([
        'project_id' => $this->project->id,
        'status' => PackageStatus::APPROVED,
    ]);

    $round = EntityFeedbackRound::create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
        'entity_id' => $package->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::SUBMITTED->value,
        'opened_by' => $this->customer->id,
        'submitted_by' => $this->customer->id,
        'submitted_at' => now(),
    ]);

    foreach ([31, 32] as $photoId) {
        EntityFeedbackNote::create([
            'feedback_round_id' => $round->id,
            'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
            'entity_id' => $package->id,
            'source_photo_id' => $photoId,
            'source_photo_type' => 'photo',
            'original_image_url' => "https://example.test/photo-{$photoId}.jpg",
            'body' => 'Issue on photo '.$photoId.', please fix.',
            'created_by' => $this->customer->id,
        ]);
    }

    // A live issue is the reason an approved package stays editable at all.
    expect($package->fresh()->hasPendingFeedback())->toBeTrue();

    // Forwarding the review to a technician is the other feedback unlock.
    $round->update(['assigned_at' => now(), 'assigned_to_user_id' => $this->technician->id]);
    expect($package->fresh()->hasActiveFeedbackAssignedToTechnician())->toBeTrue();

    $round->notes()->update(['withdrawn_at' => now(), 'withdrawn_by' => $this->customer->id]);

    // Nothing left to act on, so the lock holds: no user - manager, technician or
    // admin - can modify an image, a section, or the package draft through either
    // feedback unlock.
    expect($package->fresh()->hasPendingFeedback())->toBeFalse()
        ->and($package->fresh()->hasActiveFeedbackAssignedToTechnician())->toBeFalse();
});

/**
 * An approved package with one category holding two photos, wired through a
 * template category and section - the shape a withdrawn photo, category, or
 * section issue has to be matched against.
 *
 * @return array{CloseoutPackage, PackageCategorySelection, Photo, Photo, EntityFeedbackRound}
 */
function packageWithPhotos(User $customer, Project $project): array
{
    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'status' => PackageStatus::APPROVED,
    ]);
    $stage = PackageStage::factory()->create(['closeout_package_id' => $package->id]);
    $selection = PackageCategorySelection::factory()->create(['package_stage_id' => $stage->id]);

    $photos = collect([1, 2])->map(fn (int $position): Photo => Photo::factory()->create([
        'closeout_package_id' => $package->id,
        'package_stage_id' => $stage->id,
        'package_category_selection_id' => $selection->id,
        'position' => $position,
    ]));

    $round = EntityFeedbackRound::create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
        'entity_id' => $package->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::SUBMITTED->value,
        'opened_by' => $customer->id,
        'submitted_by' => $customer->id,
        'submitted_at' => now(),
    ]);

    return [$package, $selection, $photos[0], $photos[1], $round];
}

function withdrawnNoteOn(EntityFeedbackRound $round, User $customer, int $targetId, ?string $type): EntityFeedbackNote
{
    return EntityFeedbackNote::create([
        'feedback_round_id' => $round->id,
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
        'entity_id' => $round->entity_id,
        'source_photo_id' => $targetId,
        'source_photo_type' => $type,
        'original_image_url' => 'https://example.test/photo.jpg',
        'body' => 'Fix this, please.',
        'created_by' => $customer->id,
        'withdrawn_at' => now(),
        'withdrawn_by' => $customer->id,
    ]);
}

it('freezes the photo whose issue was withdrawn, and only that photo', function (): void {
    [$package,, $photo, $sibling, $round] = packageWithPhotos($this->customer, $this->project);
    $guard = app(AssertFeedbackTargetNotWithdrawn::class);
    $withdrawn = withdrawnNoteOn($round, $this->customer, $photo->id, 'photo');

    expect(fn () => $guard->forPhoto($package, $photo))->toThrow(InvalidStateTransitionException::class)
        // The other picture in the same category carries no complaint at all.
        ->and(fn () => $guard->forPhoto($package, $sibling))->not->toThrow(InvalidStateTransitionException::class);

    // One live issue on the frozen photo is work the customer did take back.
    EntityFeedbackNote::create([
        'feedback_round_id' => $round->id,
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
        'entity_id' => $package->id,
        'source_photo_id' => $photo->id,
        'source_photo_type' => 'photo',
        'body' => 'And the trim is wrong too.',
        'created_by' => $this->customer->id,
    ]);

    expect(fn () => $guard->forPhoto($package, $photo->fresh()))
        ->not->toThrow(InvalidStateTransitionException::class)
        ->and($withdrawn->fresh()->isWithdrawn())->toBeTrue();
});

it('freezes a category for a withdrawn category or section issue whatever id space it used', function (): void {
    [$package, $selection,,, $round] = packageWithPhotos($this->customer, $this->project);
    $guard = app(AssertFeedbackTargetNotWithdrawn::class);
    $category = $selection->templatePictureCategory;

    // The customer annotated the category row, which the client files under the
    // template category's id - not the package's selection id.
    withdrawnNoteOn($round, $this->customer, (int) $category->id, 'category');

    expect(fn () => $guard->forCategorySelection($package, $selection))
        ->toThrow(InvalidStateTransitionException::class);

    // An issue dropped against the whole section covers every category in it.
    $round->notes()->delete();
    withdrawnNoteOn($round, $this->customer, (int) $category->template_section_id, 'section');

    expect(fn () => $guard->forCategorySelection($package, $selection->id))
        ->toThrow(InvalidStateTransitionException::class);

    // The very same number filed as a photo message is a different id space -
    // section, category and photo autoincrements overlap on a bare id, and
    // matching on the number alone would freeze the wrong item.
    $round->notes()->update(['source_photo_type' => 'photo']);

    expect(fn () => $guard->forCategorySelection($package, $selection))
        ->not->toThrow(InvalidStateTransitionException::class);
});

it('refuses the delete endpoint itself, not just the highlight, on a withdrawn photo', function (): void {
    [$package,, $photo,, $round] = packageWithPhotos($this->customer, $this->project);

    // The review still asks for work elsewhere, so the package stays open...
    withdrawnNoteOn($round, $this->customer, $photo->id, 'photo');
    EntityFeedbackNote::create([
        'feedback_round_id' => $round->id,
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
        'entity_id' => $package->id,
        'source_photo_id' => 9999,
        'source_photo_type' => 'photo',
        'body' => 'Something else is still wrong.',
        'created_by' => $this->customer->id,
    ]);

    expect($package->fresh()->hasPendingFeedback())->toBeTrue();

    // ...and the action still refuses this one picture.
    expect(fn () => app(DeletePhotoAction::class)->execute($photo->fresh()))
        ->toThrow(InvalidStateTransitionException::class)
        ->and(Photo::query()->whereKey($photo->id)->exists())->toBeTrue();
});
