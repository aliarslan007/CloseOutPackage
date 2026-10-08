<?php

declare(strict_types=1);

use App\Domains\Projects\Enums\IssueType;
use App\Domains\Projects\Models\IssueHistory;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Storage::fake('public');
    config()->set('filesystems.disks.public.disk_local.root', storage_path('framework/testing/disks/public'));
});

function makeIssueWithReporter(User $reporter, ?Project $project = null): IssueHistory
{
    $project ??= Project::factory()->create();

    return IssueHistory::factory()->for($project)->create([
        'reported_by' => $reporter->id,
        'type' => IssueType::LOCK,
        'description' => 'Gate is locked.',
    ]);
}

function attachMediaToIssue(IssueHistory $issue, ?string $name = null): Media
{
    return $issue
        ->addMedia(UploadedFile::fake()->image($name ?? Str::random(8).'.jpg'))
        ->toMediaCollection(IssueHistory::MEDIA_COLLECTION);
}

it('lets a manager add photos to any issue', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $reporter = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $issue = makeIssueWithReporter($reporter);

    test()->actingAs($manager)
        ->post(route('projects.issues.attachments.store', [$issue->project, $issue]), [
            'attachments' => [
                UploadedFile::fake()->image('site.jpg'),
                UploadedFile::fake()->create('clip.mp4', 512, 'video/mp4'),
            ],
        ])
        ->assertRedirect(route('projects.issues.index', $issue->project))
        ->assertSessionHas('success');

    expect($issue->fresh()->getMedia(IssueHistory::MEDIA_COLLECTION)->count())->toBe(2);
});

it('lets a field supervisor add media only to their own issue', function (): void {
    $mine = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $theirs = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $mineIssue = makeIssueWithReporter($mine);
    $theirIssue = makeIssueWithReporter($theirs, $mineIssue->project);

    test()->actingAs($mine)
        ->post(route('projects.issues.attachments.store', [$mineIssue->project, $mineIssue]), [
            'attachments' => [UploadedFile::fake()->image('own.jpg')],
        ])
        ->assertRedirect();

    expect($mineIssue->fresh()->getMedia(IssueHistory::MEDIA_COLLECTION)->count())->toBe(1);

    test()->actingAs($mine)
        ->postJson(route('projects.issues.attachments.store', [$theirIssue->project, $theirIssue]), [
            'attachments' => [UploadedFile::fake()->image('other.jpg')],
        ])
        ->assertForbidden();

    expect($theirIssue->fresh()->getMedia(IssueHistory::MEDIA_COLLECTION)->count())->toBe(0);
});

it('rejects customers from editing issue media', function (): void {
    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    $issue = makeIssueWithReporter(User::factory()->create()->assignRole(Role::MANAGER->value));

    test()->actingAs($customer)
        ->postJson(route('projects.issues.attachments.store', [$issue->project, $issue]), [
            'attachments' => [UploadedFile::fake()->image('x.jpg')],
        ])
        ->assertForbidden();
});

it('enforces the remaining slot cap when adding media', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $issue = makeIssueWithReporter(User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value));

    foreach (range(1, 9) as $i) {
        attachMediaToIssue($issue, "existing{$i}.jpg");
    }

    test()->actingAs($manager)
        ->post(route('projects.issues.attachments.store', [$issue->project, $issue]), [
            'attachments' => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.jpg'),
            ],
        ])
        ->assertSessionHasErrors('attachments');

    expect($issue->fresh()->getMedia(IssueHistory::MEDIA_COLLECTION)->count())->toBe(9);

    test()->actingAs($manager)
        ->post(route('projects.issues.attachments.store', [$issue->project, $issue]), [
            'attachments' => [UploadedFile::fake()->image('fits.jpg')],
        ])
        ->assertRedirect();

    expect($issue->fresh()->getMedia(IssueHistory::MEDIA_COLLECTION)->count())->toBe(10);
});

it('rejects oversized and unsupported files when adding media', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $issue = makeIssueWithReporter(User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value));

    test()->actingAs($manager)
        ->post(route('projects.issues.attachments.store', [$issue->project, $issue]), [
            'attachments' => [UploadedFile::fake()->create('big.mp4', 6 * 1024, 'video/mp4')],
        ])
        ->assertSessionHasErrors('attachments.0');

    test()->actingAs($manager)
        ->post(route('projects.issues.attachments.store', [$issue->project, $issue]), [
            'attachments' => [UploadedFile::fake()->create('notes.txt', 10, 'text/plain')],
        ])
        ->assertSessionHasErrors('attachments.0');

    expect($issue->fresh()->getMedia(IssueHistory::MEDIA_COLLECTION)->count())->toBe(0);
});

it('requires at least one file when adding media', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $issue = makeIssueWithReporter(User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value));

    test()->actingAs($manager)
        ->post(route('projects.issues.attachments.store', [$issue->project, $issue]), [])
        ->assertSessionHasErrors('attachments');
});

it('lets admins and managers remove media from any issue', function (): void {
    $reporter = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $issue = makeIssueWithReporter($reporter);
    $media = attachMediaToIssue($issue);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);

    test()->actingAs($manager)
        ->delete(route('projects.issues.attachments.destroy', [$issue->project, $issue, $media]))
        ->assertRedirect(route('projects.issues.index', $issue->project));

    expect($issue->fresh()->getMedia(IssueHistory::MEDIA_COLLECTION)->count())->toBe(0);
});

it('lets a field supervisor remove media only from their own issue', function (): void {
    $mine = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $theirs = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $myIssue = makeIssueWithReporter($mine);
    $theirIssue = makeIssueWithReporter($theirs, $myIssue->project);
    $theirMedia = attachMediaToIssue($theirIssue);

    test()->actingAs($mine)
        ->deleteJson(route('projects.issues.attachments.destroy', [$theirIssue->project, $theirIssue, $theirMedia]))
        ->assertForbidden();

    expect($theirIssue->fresh()->getMedia(IssueHistory::MEDIA_COLLECTION)->count())->toBe(1);

    $myMedia = attachMediaToIssue($myIssue);
    test()->actingAs($mine)
        ->delete(route('projects.issues.attachments.destroy', [$myIssue->project, $myIssue, $myMedia]))
        ->assertRedirect();

    expect($myIssue->fresh()->getMedia(IssueHistory::MEDIA_COLLECTION)->count())->toBe(0);
});

it('refuses to delete media that belongs to a different issue', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $issueA = makeIssueWithReporter(User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value));
    $issueB = makeIssueWithReporter(User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value), $issueA->project);
    $mediaB = attachMediaToIssue($issueB);

    test()->actingAs($manager)
        ->delete(route('projects.issues.attachments.destroy', [$issueA->project, $issueA, $mediaB]))
        ->assertNotFound();

    expect($issueB->fresh()->getMedia(IssueHistory::MEDIA_COLLECTION)->count())->toBe(1);
});

it('locks media editing on resolved issues unless the config unlocks it', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $issue = makeIssueWithReporter(User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value));
    $issue->update(['resolved_at' => now()]);

    test()->actingAs($manager)
        ->postJson(route('projects.issues.attachments.store', [$issue->project, $issue]), [
            'attachments' => [UploadedFile::fake()->image('late.jpg')],
        ])
        ->assertForbidden();

    $media = attachMediaToIssue($issue);
    test()->actingAs($manager)
        ->deleteJson(route('projects.issues.attachments.destroy', [$issue->project, $issue, $media]))
        ->assertForbidden();

    // One config flip re-opens editing — no code change needed.
    config()->set('issues.allow_media_edit_when_resolved', true);

    test()->actingAs($manager)
        ->post(route('projects.issues.attachments.store', [$issue->project, $issue]), [
            'attachments' => [UploadedFile::fake()->image('late.jpg')],
        ])
        ->assertRedirect();

    expect($issue->fresh()->getMedia(IssueHistory::MEDIA_COLLECTION)->count())->toBe(2);
});

it('downscales an oversized image attached to an issue', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $reporter = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $issue = makeIssueWithReporter($reporter);

    test()->actingAs($manager)
        ->post(route('projects.issues.attachments.store', [$issue->project, $issue]), [
            'attachments' => [copTestImage('image/jpeg', 3000, 2000)],
        ])
        ->assertSessionHasNoErrors();

    $media = $issue->fresh()->getMedia(IssueHistory::MEDIA_COLLECTION)->first();

    expect($media)->not->toBeNull()
        ->and(copTestImageDimension($media->getPath()))->toBeLessThanOrEqual(2560);
});

it('stores video attachments untouched without attempting image compression', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $reporter = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $issue = makeIssueWithReporter($reporter);

    test()->actingAs($manager)
        ->post(route('projects.issues.attachments.store', [$issue->project, $issue]), [
            'attachments' => [UploadedFile::fake()->create('clip.mp4', 1024, 'video/mp4')],
        ])
        ->assertSessionHasNoErrors();

    $media = $issue->fresh()->getMedia(IssueHistory::MEDIA_COLLECTION)->first();

    expect($media)->not->toBeNull()
        ->and($media->file_name)->toBe('clip.mp4')
        ->and($media->collection_name)->toBe(IssueHistory::MEDIA_COLLECTION);
});
