<?php

declare(strict_types=1);

use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\ChangeOrders\Models\ChangeOrderPhoto;
use App\Domains\ChangeOrders\Models\ChangeOrderRecipient;
use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\EntityFeedback\Enums\FeedbackEntityType;
use App\Domains\EntityFeedback\Enums\FeedbackRoundStatus;
use App\Domains\EntityFeedback\Models\EntityFeedbackNote;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\EntityFeedback\Notifications\CustomerFeedbackSubmittedNotification;
use App\Domains\EntityFeedback\Notifications\FeedbackForwardedToTechnicianNotification;
use App\Domains\EntityFeedback\Notifications\FeedbackResponseReadyNotification;
use App\Domains\EntityFeedback\Presenters\FeedbackRoundPresenter;
use App\Domains\Markets\Models\Market;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\Photos\Enums\PhotoStatus;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Models\IssueHistory;
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
    Storage::fake('public');
    Storage::fake('media');
    Notification::fake();
});

function createEntityFeedbackFixture(): array
{
    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $technician = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);

    $project = Project::factory()->create([
        'market_id' => $market->id,
        'manager_id' => $manager->id,
    ]);

    $project->fieldSupervisors()->attach($technician->id);

    // The customer is a real customer: the policy scopes customer abilities to
    // projects they were granted, so the fixture has to grant it.
    CustomerProjectAccess::factory()->create([
        'user_id' => $customer->id,
        'project_id' => $project->id,
    ]);

    $changeOrder = ChangeOrder::factory()->create([
        'project_id' => $project->id,
        'created_by' => $manager->id,
    ]);

    return [
        'manager' => $manager,
        'technician' => $technician,
        'customer' => $customer,
        'project' => $project,
        'changeOrder' => $changeOrder,
    ];
}

it('allows customer to open a feedback round on a change order', function (): void {
    $fixture = createEntityFeedbackFixture();

    $response = $this->actingAs($fixture['customer'])
        ->post(route('entity-feedback.rounds.open'), [
            'entity_type' => FeedbackEntityType::CHANGE_ORDER->value,
            'entity_id' => $fixture['changeOrder']->id,
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('entity_feedback_rounds', [
        'entity_type' => FeedbackEntityType::CHANGE_ORDER->value,
        'entity_id' => $fixture['changeOrder']->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::OPEN->value,
    ]);
});

it('allows customer to add an annotated review note to open round', function (): void {
    $fixture = createEntityFeedbackFixture();

    /** @var EntityFeedbackRound $round */
    $round = EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::CHANGE_ORDER,
        'entity_id' => $fixture['changeOrder']->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::OPEN,
        'opened_by' => $fixture['customer']->id,
    ]);

    $fakeImage = UploadedFile::fake()->image('annotated_sample.jpg', 600, 400);

    $response = $this->actingAs($fixture['customer'])
        ->post(route('entity-feedback.notes.store', $round), [
            'entity_type' => FeedbackEntityType::CHANGE_ORDER->value,
            'entity_id' => $fixture['changeOrder']->id,
            'original_image_url' => 'https://example.com/source-photo.jpg',
            'body' => 'Why is this conduit positioned here?',
            'annotated_image' => $fakeImage,
            'annotation_layer' => json_encode(['nodes' => [['type' => 'box', 'x' => 10, 'y' => 20]]]),
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('entity_feedback_notes', [
        'feedback_round_id' => $round->id,
        'body' => 'Why is this conduit positioned here?',
    ]);
});

it('allows customer to submit feedback round to manager and notifies manager', function (): void {
    $fixture = createEntityFeedbackFixture();

    /** @var EntityFeedbackRound $round */
    $round = EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::CHANGE_ORDER,
        'entity_id' => $fixture['changeOrder']->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::OPEN,
        'opened_by' => $fixture['customer']->id,
    ]);

    $round->notes()->create([
        'entity_type' => FeedbackEntityType::CHANGE_ORDER,
        'entity_id' => $fixture['changeOrder']->id,
        'original_image_url' => 'https://example.com/source.jpg',
        'body' => 'Please explain the wire routing.',
        'created_by' => $fixture['customer']->id,
    ]);

    $response = $this->actingAs($fixture['customer'])
        ->post(route('entity-feedback.rounds.submit', $round));

    $response->assertRedirect();
    expect($round->fresh()->status)->toBe(FeedbackRoundStatus::SUBMITTED);
    expect($round->fresh()->submitted_at)->not->toBeNull();

    Notification::assertSentTo(
        $fixture['manager'],
        CustomerFeedbackSubmittedNotification::class
    );
});

it('allows manager to delegate feedback round to technician with instructions', function (): void {
    $fixture = createEntityFeedbackFixture();

    // Delegation belongs to the site-survey entities. A change order is raised
    // and handled by a manager, so the workflow is not offered there — that
    // refusal is covered in EntityFeedbackLifecycleTest.
    $issue = IssueHistory::factory()->create([
        'project_id' => $fixture['project']->id,
        'reported_by' => $fixture['customer']->id,
    ]);

    /** @var EntityFeedbackRound $round */
    $round = EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::ISSUE,
        'entity_id' => $issue->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::SUBMITTED,
        'opened_by' => $fixture['customer']->id,
        'submitted_at' => now(),
        'submitted_by' => $fixture['customer']->id,
    ]);

    $response = $this->actingAs($fixture['manager'])
        ->post(route('entity-feedback.rounds.forward-technician', $round), [
            'assigned_to_user_id' => $fixture['technician']->id,
            'instructions' => 'Please re-run conduit and take updated photo.',
        ]);

    $response->assertRedirect();
    $freshRound = $round->fresh();
    expect($freshRound->assigned_to_user_id)->toBe($fixture['technician']->id);
    expect($freshRound->isAssignedToTechnician())->toBeTrue();
    expect($freshRound->manager_instructions)->toContain('Please re-run conduit');

    Notification::assertSentTo(
        $fixture['technician'],
        FeedbackForwardedToTechnicianNotification::class
    );
    Notification::assertSentTo(
        $fixture['customer'],
        FeedbackForwardedToTechnicianNotification::class
    );
});

it('notifies all assigned project technicians when delegated without specific technician id', function (): void {
    $fixture = createEntityFeedbackFixture();

    $issue = IssueHistory::factory()->create([
        'project_id' => $fixture['project']->id,
        'reported_by' => $fixture['customer']->id,
    ]);

    /** @var EntityFeedbackRound $round */
    $round = EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::ISSUE,
        'entity_id' => $issue->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::SUBMITTED,
        'opened_by' => $fixture['customer']->id,
        'submitted_at' => now(),
        'submitted_by' => $fixture['customer']->id,
    ]);

    $response = $this->actingAs($fixture['manager'])
        ->post(route('entity-feedback.rounds.forward-technician', $round), [
            'assigned_to_user_id' => null,
            'instructions' => 'Team, please address customer notes.',
        ]);

    $response->assertRedirect();
    $freshRound = $round->fresh();
    expect($freshRound->assigned_to_user_id)->toBeNull();
    expect($freshRound->assigned_at)->not->toBeNull();
    expect($freshRound->manager_instructions)->toContain('Team, please address');

    Notification::assertSentTo(
        $fixture['technician'],
        FeedbackForwardedToTechnicianNotification::class
    );
    Notification::assertSentTo(
        $fixture['customer'],
        FeedbackForwardedToTechnicianNotification::class
    );
});

it('allows technician to submit resolutions back to manager', function (): void {
    $fixture = createEntityFeedbackFixture();

    /** @var EntityFeedbackRound $round */
    $round = EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::CHANGE_ORDER,
        'entity_id' => $fixture['changeOrder']->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::SUBMITTED,
        'opened_by' => $fixture['customer']->id,
        'submitted_at' => now(),
        'assigned_to_user_id' => $fixture['technician']->id,
        'assigned_at' => now(),
    ]);

    $response = $this->actingAs($fixture['technician'])
        ->post(route('entity-feedback.rounds.submit-manager', $round));

    $response->assertRedirect();
    $freshRound = $round->fresh();
    expect($freshRound->isTechnicianSubmitted())->toBeTrue();
    expect($freshRound->technician_submitted_by)->toBe($fixture['technician']->id);
});

it('prevents technician from submitting revisions to manager multiple times', function (): void {
    $fixture = createEntityFeedbackFixture();

    /** @var EntityFeedbackRound $round */
    $round = EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::CHANGE_ORDER,
        'entity_id' => $fixture['changeOrder']->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::SUBMITTED,
        'opened_by' => $fixture['customer']->id,
        'submitted_at' => now(),
        'assigned_to_user_id' => $fixture['technician']->id,
        'assigned_at' => now(),
        'technician_submitted_at' => now(),
        'technician_submitted_by' => $fixture['technician']->id,
    ]);

    $response = $this->actingAs($fixture['technician'])
        ->post(route('entity-feedback.rounds.submit-manager', $round));

    $response->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');
});

it('allows manager to resolve all notes and transition round to resolved', function (): void {
    $fixture = createEntityFeedbackFixture();

    /** @var EntityFeedbackRound $round */
    $round = EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::CHANGE_ORDER,
        'entity_id' => $fixture['changeOrder']->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::SUBMITTED,
        'opened_by' => $fixture['customer']->id,
        'submitted_at' => now(),
        'submitted_by' => $fixture['customer']->id,
    ]);

    /** @var EntityFeedbackNote $note */
    $note = $round->notes()->create([
        'entity_type' => FeedbackEntityType::CHANGE_ORDER,
        'entity_id' => $fixture['changeOrder']->id,
        'original_image_url' => 'https://example.com/source.jpg',
        'body' => 'Need cable replacement.',
        'created_by' => $fixture['customer']->id,
    ]);

    // Resolve note individually first
    $this->actingAs($fixture['manager'])
        ->patch(route('entity-feedback.notes.resolve', $note), [
            'manager_response' => 'Replaced cable per specification.',
        ])
        ->assertRedirect();

    expect($note->fresh()->isResolved())->toBeTrue();
    expect($note->fresh()->manager_response)->toBe('Replaced cable per specification.');

    // Resolve all and send to customer
    $response = $this->actingAs($fixture['manager'])
        ->post(route('entity-feedback.rounds.resolve-all', $round));

    $response->assertRedirect();
    expect($round->fresh()->status)->toBe(FeedbackRoundStatus::RESOLVED);
    expect($round->fresh()->resolved_at)->not->toBeNull();

    Notification::assertSentTo(
        $fixture['customer'],
        FeedbackResponseReadyNotification::class
    );
});

it('allows customer to accept resolved feedback round', function (): void {
    $fixture = createEntityFeedbackFixture();

    /** @var EntityFeedbackRound $round */
    $round = EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::CHANGE_ORDER,
        'entity_id' => $fixture['changeOrder']->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::RESOLVED,
        'opened_by' => $fixture['customer']->id,
        'submitted_at' => now(),
        'submitted_by' => $fixture['customer']->id,
        'resolved_at' => now(),
        'resolved_by' => $fixture['manager']->id,
    ]);

    $response = $this->actingAs($fixture['customer'])
        ->post(route('entity-feedback.rounds.accept', $round));

    $response->assertRedirect();
    expect($round->fresh()->status)->toBe(FeedbackRoundStatus::ACCEPTED);
    expect($round->fresh()->accepted_at)->not->toBeNull();
});

it('auto-acknowledges round when manager sends a message on unacknowledged round', function (): void {
    $fixture = createEntityFeedbackFixture();

    /** @var EntityFeedbackRound $round */
    $round = EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::CHANGE_ORDER,
        'entity_id' => $fixture['changeOrder']->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::SUBMITTED,
        'opened_by' => $fixture['customer']->id,
        'submitted_at' => now(),
        'submitted_by' => $fixture['customer']->id,
    ]);

    expect($round->manager_acknowledged_at)->toBeNull();

    $response = $this->actingAs($fixture['manager'])
        ->post(route('entity-feedback.notes.store', $round), [
            'entity_type' => FeedbackEntityType::CHANGE_ORDER->value,
            'entity_id' => $fixture['changeOrder']->id,
            'body' => 'I am looking into this now.',
        ]);

    $response->assertRedirect();
    $freshRound = $round->fresh();
    expect($freshRound->manager_acknowledged_at)->not->toBeNull();
    expect($freshRound->manager_acknowledged_by)->toBe($fixture['manager']->id);
});

it('approves pending package photos when manager resolves feedback round', function (): void {
    $fixture = createEntityFeedbackFixture();

    $package = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
    ]);

    /** @var EntityFeedbackRound $round */
    $round = EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE,
        'entity_id' => $package->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::SUBMITTED,
        'opened_by' => $fixture['customer']->id,
        'submitted_at' => now(),
        'submitted_by' => $fixture['customer']->id,
    ]);

    $round->notes()->create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE,
        'entity_id' => $package->id,
        'body' => 'Please fix this photo.',
        'created_by' => $fixture['customer']->id,
    ]);

    $pendingPhoto = Photo::factory()->create([
        'closeout_package_id' => $package->id,
        'status' => PhotoStatus::PENDING,
        'is_approved' => null,
    ]);

    $response = $this->actingAs($fixture['manager'])
        ->post(route('entity-feedback.rounds.resolve-all', $round), [
            'general_response' => 'All fixed and ready for review.',
        ]);

    $response->assertRedirect();
    expect($round->fresh()->status)->toBe(FeedbackRoundStatus::RESOLVED);

    $freshPhoto = $pendingPhoto->fresh();
    expect($freshPhoto->status)->toBe(PhotoStatus::APPROVED);
    expect($freshPhoto->is_approved)->toBeTrue();
    expect($freshPhoto->approved_at)->not->toBeNull();
    expect($freshPhoto->approved_by)->toBe($fixture['manager']->id);
});

it('allows replacing a photo on an approved package when feedback rework is active', function (): void {
    $fixture = createEntityFeedbackFixture();

    $package = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'status' => PackageStatus::APPROVED,
        'approved_at' => now(),
        'approved_by' => $fixture['manager']->id,
    ]);

    /** @var EntityFeedbackRound $round */
    $round = EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE,
        'entity_id' => $package->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::SUBMITTED,
        'opened_by' => $fixture['customer']->id,
        'submitted_at' => now(),
        'submitted_by' => $fixture['customer']->id,
        'assigned_to_user_id' => $fixture['technician']->id,
        'assigned_at' => now(),
    ]);

    $photo = Photo::factory()->create([
        'closeout_package_id' => $package->id,
        'status' => PhotoStatus::APPROVED,
        'is_approved' => true,
    ]);

    $note = $round->notes()->create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE,
        'entity_id' => $package->id,
        'source_photo_type' => 'photo',
        'source_photo_id' => $photo->id,
        'body' => 'Need replacement photo with better lighting.',
        'created_by' => $fixture['customer']->id,
    ]);

    $response = $this->actingAs($fixture['technician'])
        ->post(route('photos.replace', [$package, $photo]), [
            'photo' => UploadedFile::fake()->image('replacement.jpg'),
            'photo_id' => (string) $photo->id,
        ]);

    $response->assertRedirect();
    $photo->refresh();
    expect($photo->replaced_at)->not->toBeNull();
    expect($photo->replaced_by_photo_id)->not->toBeNull();

    // The complaint follows the replacement and stays open, so the photo node
    // remains visible to the manager and technician until the manager resolves
    // the round. Closing it here would let a technician self-close the query.
    $note->refresh();
    expect($note->source_photo_id)->toBe($photo->replaced_by_photo_id)
        ->and($note->resolved_at)->toBeNull()
        ->and($note->manager_response)->toBeNull();
});

it('blocks replacing a photo on an approved package when no feedback is active', function (): void {
    $fixture = createEntityFeedbackFixture();

    $package = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'status' => PackageStatus::APPROVED,
        'approved_at' => now(),
        'approved_by' => $fixture['manager']->id,
    ]);

    $photo = Photo::factory()->create([
        'closeout_package_id' => $package->id,
        'status' => PhotoStatus::APPROVED,
        'is_approved' => true,
    ]);

    $response = $this->actingAs($fixture['technician'])
        ->post(route('photos.replace', [$package, $photo]), [
            'photo' => UploadedFile::fake()->image('replacement.jpg'),
            'photo_id' => (string) $photo->id,
        ]);

    $response->assertRedirect();
    $photo->refresh();
    expect($photo->replaced_at)->toBeNull();
});

it('allows customer to accept and close a feedback round at any time', function (): void {
    $fixture = createEntityFeedbackFixture();

    /** @var EntityFeedbackRound $round */
    $round = EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::CHANGE_ORDER,
        'entity_id' => $fixture['changeOrder']->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::SUBMITTED,
        'opened_by' => $fixture['customer']->id,
        'submitted_at' => now(),
        'submitted_by' => $fixture['customer']->id,
    ]);

    $note = $round->notes()->create([
        'entity_type' => FeedbackEntityType::CHANGE_ORDER,
        'entity_id' => $fixture['changeOrder']->id,
        'body' => 'Need adjustment.',
        'created_by' => $fixture['customer']->id,
    ]);

    $response = $this->actingAs($fixture['customer'])
        ->post(route('entity-feedback.rounds.accept', $round));

    $response->assertRedirect();
    expect($round->fresh()->status)->toBe(FeedbackRoundStatus::ACCEPTED);
    expect($note->fresh()->is_resolved)->toBeTrue();
});

it('allows guest recipient to accept and close feedback round via decision token', function (): void {
    $fixture = createEntityFeedbackFixture();
    $token = 'test-guest-token-123';

    ChangeOrderRecipient::factory()->create([
        'change_order_id' => $fixture['changeOrder']->id,
        'user_id' => $fixture['customer']->id,
        'token_hash' => ChangeOrderRecipient::hashToken($token),
        'token_expires_at' => now()->addDays(7),
    ]);

    /** @var EntityFeedbackRound $round */
    $round = EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::CHANGE_ORDER,
        'entity_id' => $fixture['changeOrder']->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::SUBMITTED,
        'opened_by' => $fixture['customer']->id,
        'submitted_at' => now(),
        'submitted_by' => $fixture['customer']->id,
    ]);

    $response = $this->post(route('change-orders.token.feedback.accept', [
        'token' => $token,
        'round' => $round,
    ]));

    $response->assertRedirect();
    expect($round->fresh()->status)->toBe(FeedbackRoundStatus::ACCEPTED);
});

it('allows creating feedback note with replacement photo and no text body', function (): void {
    $fixture = createEntityFeedbackFixture();

    /** @var EntityFeedbackRound $round */
    $round = EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::CHANGE_ORDER,
        'entity_id' => $fixture['changeOrder']->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::SUBMITTED,
        'opened_by' => $fixture['customer']->id,
        'submitted_at' => now(),
        'submitted_by' => $fixture['customer']->id,
    ]);

    $response = $this->actingAs($fixture['manager'])
        ->post(route('entity-feedback.notes.store', $round), [
            'entity_type' => FeedbackEntityType::CHANGE_ORDER->value,
            'entity_id' => $fixture['changeOrder']->id,
            'replacement_image' => UploadedFile::fake()->image('replacement.jpg'),
            'add_to_change_order_photos' => '1',
        ]);

    $response->assertRedirect();
    $note = $round->notes()->latest('id')->first();
    expect($note)->not->toBeNull();
    expect($note->body)->toBe('Attached replacement photo.');
    expect($fixture['changeOrder']->photos()->count())->toBe(1);
});

it('replaces target ChangeOrderPhoto when replace_in_change_order is specified on note resolution', function (): void {
    $fixture = createEntityFeedbackFixture();

    /** @var ChangeOrderPhoto $photo */
    $photo = $fixture['changeOrder']->photos()->create([
        'uploaded_by' => $fixture['manager']->id,
        'original_name' => 'original.jpg',
        'path' => 'change-orders/'.$fixture['changeOrder']->id.'/original.jpg',
        'mime_type' => 'image/jpeg',
        'size_bytes' => 1024,
    ]);

    /** @var EntityFeedbackRound $round */
    $round = EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::CHANGE_ORDER,
        'entity_id' => $fixture['changeOrder']->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::SUBMITTED,
        'opened_by' => $fixture['customer']->id,
        'submitted_at' => now(),
        'submitted_by' => $fixture['customer']->id,
    ]);

    $note = $round->notes()->create([
        'entity_type' => FeedbackEntityType::CHANGE_ORDER,
        'entity_id' => $fixture['changeOrder']->id,
        'source_photo_id' => $photo->id,
        'body' => 'Please replace this photo.',
        'created_by' => $fixture['customer']->id,
    ]);

    $response = $this->actingAs($fixture['manager'])
        ->patch(route('entity-feedback.notes.resolve', $note), [
            'manager_response' => 'Replaced photo as requested.',
            'replacement_image' => UploadedFile::fake()->image('new_photo.jpg'),
            'replace_in_change_order' => '1',
        ]);

    $response->assertRedirect();
    $photo->refresh();
    expect($photo->original_name)->toBe('new_photo.jpg');
});

it('replaces the flagged photo addressed by its media asset id, leaving the other photos alone', function (): void {
    // The review UI addresses change-order photos by media_asset_id, so this is
    // the real shape of source_photo_id. Replacing must hit that photo and must
    // not touch any other photo in the change order.
    $fixture = createEntityFeedbackFixture();

    $flaggedAsset = MediaAsset::factory()->create();
    $otherAsset = MediaAsset::factory()->create();

    $flagged = $fixture['changeOrder']->photos()->create([
        'uploaded_by' => $fixture['manager']->id,
        'media_asset_id' => $flaggedAsset->id,
        'original_name' => 'flagged.jpg',
        'path' => 'change-orders/'.$fixture['changeOrder']->id.'/flagged.jpg',
        'mime_type' => 'image/jpeg',
        'size_bytes' => 1024,
    ]);
    $untouched = $fixture['changeOrder']->photos()->create([
        'uploaded_by' => $fixture['manager']->id,
        'media_asset_id' => $otherAsset->id,
        'original_name' => 'other.jpg',
        'path' => 'change-orders/'.$fixture['changeOrder']->id.'/other.jpg',
        'mime_type' => 'image/jpeg',
        'size_bytes' => 1024,
    ]);

    $round = EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::CHANGE_ORDER,
        'entity_id' => $fixture['changeOrder']->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::SUBMITTED,
        'opened_by' => $fixture['customer']->id,
        'submitted_at' => now(),
        'submitted_by' => $fixture['customer']->id,
    ]);

    $note = $round->notes()->create([
        'entity_type' => FeedbackEntityType::CHANGE_ORDER,
        'entity_id' => $fixture['changeOrder']->id,
        // A media asset id, not the ChangeOrderPhoto primary key.
        'source_photo_id' => $flaggedAsset->id,
        'body' => 'This one is blurry, please retake it.',
        'created_by' => $fixture['customer']->id,
    ]);

    $this->actingAs($fixture['manager'])
        ->patch(route('entity-feedback.notes.resolve', $note), [
            'manager_response' => 'Retaken and replaced.',
            'replacement_image' => UploadedFile::fake()->image('retake.jpg'),
            'replace_in_change_order' => '1',
        ])
        ->assertRedirect();

    expect($flagged->refresh()->original_name)->toBe('retake.jpg')
        ->and($untouched->refresh()->original_name)->toBe('other.jpg');
});

it('does not guess which photo to replace when a change order has several and the id matches nothing', function (): void {
    // Overwriting the wrong photo destroys evidence the customer never
    // complained about, so an unresolvable target must be a no-op.
    $fixture = createEntityFeedbackFixture();

    $first = $fixture['changeOrder']->photos()->create([
        'uploaded_by' => $fixture['manager']->id,
        'original_name' => 'first.jpg',
        'path' => 'change-orders/'.$fixture['changeOrder']->id.'/first.jpg',
        'mime_type' => 'image/jpeg',
        'size_bytes' => 1024,
    ]);
    $second = $fixture['changeOrder']->photos()->create([
        'uploaded_by' => $fixture['manager']->id,
        'original_name' => 'second.jpg',
        'path' => 'change-orders/'.$fixture['changeOrder']->id.'/second.jpg',
        'mime_type' => 'image/jpeg',
        'size_bytes' => 1024,
    ]);

    $round = EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::CHANGE_ORDER,
        'entity_id' => $fixture['changeOrder']->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::SUBMITTED,
        'opened_by' => $fixture['customer']->id,
        'submitted_at' => now(),
        'submitted_by' => $fixture['customer']->id,
    ]);

    $note = $round->notes()->create([
        'entity_type' => FeedbackEntityType::CHANGE_ORDER,
        'entity_id' => $fixture['changeOrder']->id,
        'source_photo_id' => 999999,
        'body' => 'Section-level request.',
        'created_by' => $fixture['customer']->id,
    ]);

    $this->actingAs($fixture['manager'])
        ->patch(route('entity-feedback.notes.resolve', $note), [
            'manager_response' => 'Handled.',
            'replacement_image' => UploadedFile::fake()->image('retake.jpg'),
            'replace_in_change_order' => '1',
        ])
        ->assertRedirect();

    expect($first->refresh()->original_name)->toBe('first.jpg')
        ->and($second->refresh()->original_name)->toBe('second.jpg');
});

it('still replaces when the change order holds a single photo and no id resolves', function (): void {
    // One photo leaves no ambiguity about what "replace" can mean.
    $fixture = createEntityFeedbackFixture();

    $only = $fixture['changeOrder']->photos()->create([
        'uploaded_by' => $fixture['manager']->id,
        'original_name' => 'only.jpg',
        'path' => 'change-orders/'.$fixture['changeOrder']->id.'/only.jpg',
        'mime_type' => 'image/jpeg',
        'size_bytes' => 1024,
    ]);

    $round = EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::CHANGE_ORDER,
        'entity_id' => $fixture['changeOrder']->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::SUBMITTED,
        'opened_by' => $fixture['customer']->id,
        'submitted_at' => now(),
        'submitted_by' => $fixture['customer']->id,
    ]);

    $note = $round->notes()->create([
        'entity_type' => FeedbackEntityType::CHANGE_ORDER,
        'entity_id' => $fixture['changeOrder']->id,
        'source_photo_id' => null,
        'body' => 'Section-level request.',
        'created_by' => $fixture['customer']->id,
    ]);

    $this->actingAs($fixture['manager'])
        ->patch(route('entity-feedback.notes.resolve', $note), [
            'manager_response' => 'Handled.',
            'replacement_image' => UploadedFile::fake()->image('retake.jpg'),
            'replace_in_change_order' => '1',
        ])
        ->assertRedirect();

    expect($only->refresh()->original_name)->toBe('retake.jpg');
});

it('isolates feedback round and pending feedback indicator from unassigned technician on closeout package show page', function (): void {
    $fixture = createEntityFeedbackFixture();

    /** @var CloseoutPackage $package */
    $package = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'status' => PackageStatus::SUBMITTED->value,
    ]);

    /** @var EntityFeedbackRound $round */
    $round = EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE,
        'entity_id' => $package->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::SUBMITTED,
        'opened_by' => $fixture['customer']->id,
        'submitted_at' => now(),
        'submitted_by' => $fixture['customer']->id,
    ]);

    $response = $this->actingAs($fixture['technician'])
        ->get(route('packages.show', ['package' => $package->hash_id ?? $package->id]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Packages/Show')
        ->where('feedbackRound', null)
        ->where('has_pending_feedback', false)
    );

    // Now assign to this technician
    $round->update([
        'assigned_at' => now(),
        'assigned_to_user_id' => $fixture['technician']->id,
    ]);

    $responseAssigned = $this->actingAs($fixture['technician'])
        ->get(route('packages.show', ['package' => $package->hash_id ?? $package->id]));

    $responseAssigned->assertOk();
    $responseAssigned->assertInertia(fn ($page) => $page
        ->component('Packages/Show')
        ->where('feedbackRound.id', $round->id)
        ->where('has_pending_feedback', true)
    );
});

it('presents staff attached replacement photo to customer viewer', function (): void {
    $fixture = createEntityFeedbackFixture();

    /** @var CloseoutPackage $package */
    $package = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'status' => PackageStatus::SUBMITTED->value,
    ]);

    /** @var EntityFeedbackRound $round */
    $round = EntityFeedbackRound::query()->create([
        'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE,
        'entity_id' => $package->id,
        'round_number' => 1,
        'status' => FeedbackRoundStatus::SUBMITTED,
        'opened_by' => $fixture['customer']->id,
        'submitted_at' => now(),
        'submitted_by' => $fixture['customer']->id,
    ]);

    $response = $this->actingAs($fixture['manager'])
        ->post(route('entity-feedback.notes.store', $round), [
            'entity_type' => FeedbackEntityType::CLOSEOUT_PACKAGE->value,
            'entity_id' => $package->id,
            'body' => 'Here is the replacement photo from our inspection.',
            'replacement_image' => UploadedFile::fake()->image('staff_attachment.jpg'),
        ]);

    $response->assertRedirect();

    $presenter = app(FeedbackRoundPresenter::class);
    $presented = $presenter->present($round->fresh(), $fixture['customer']);

    expect($presented)->not->toBeNull();
    $note = $presented['notes'][0] ?? null;
    expect($note)->not->toBeNull();
    expect($note['replacement_image_url'])->not->toBeNull();
    expect($note['replacement_media_id'])->not->toBeNull();
});
