<?php

declare(strict_types=1);

use App\Domains\Markets\Models\Market;
use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\Projects\Models\Project;
use App\Domains\ScopeOfWork\Enums\CompositeStatus;
use App\Domains\ScopeOfWork\Enums\SowStatus;
use App\Domains\ScopeOfWork\Models\SowAnnotatedImage;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Storage::fake('private');
});

function sowDeleteFixture(): array
{
    $market = Market::factory()->create(['is_active' => true]);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $supervisor->markets()->attach($market->id);
    $project->fieldSupervisors()->attach($supervisor->id);
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    return compact('market', 'manager', 'project', 'supervisor', 'admin');
}

function sowDraftReport($test, User $creator, Market $market, ?Project $project, string $title): SowReport
{
    $payload = ['market_id' => $market->id, 'title' => $title];
    if ($project !== null) {
        $payload['project_id'] = $project->id;
    } else {
        $payload['site_name'] = $title.' Site';
    }

    $test->actingAs($creator)->post(route('sow.reports.store'), $payload);

    return SowReport::where('title', $title)->firstOrFail();
}

function sowReadyAsset(User $owner, Market $market, ?Project $project = null): MediaAsset
{
    return MediaAsset::factory()->create([
        'user_id' => $owner->id,
        'market_id' => $market->id,
        'project_id' => $project?->id,
        'status' => MediaAssetStatus::READY->value,
    ]);
}

function sowAttachPhoto(SowReport $report, MediaAsset $asset): SowAnnotatedImage
{
    return SowAnnotatedImage::create([
        'sow_report_id' => $report->id,
        'media_asset_id' => $asset->id,
        'annotation_layer' => [],
        'composite_status' => CompositeStatus::NOT_NEEDED,
        'sort_order' => 1,
        'is_cover' => false,
        'include_in_pdf' => true,
    ]);
}

function sowDeletePhoto($test, User $user, SowReport $report, SowAnnotatedImage $image)
{
    return $test->actingAs($user)->deleteJson(route('sow.images.destroy', [
        'report' => $report->id,
        'image' => $image->id,
    ]));
}

it('lets a supervisor delete their own photo in a draft SOW', function (): void {
    $fixture = sowDeleteFixture();
    $report = sowDraftReport($this, $fixture['supervisor'], $fixture['market'], $fixture['project'], 'Draft Own Photo');
    $image = sowAttachPhoto($report, sowReadyAsset($fixture['supervisor'], $fixture['market']));

    sowDeletePhoto($this, $fixture['supervisor'], $report, $image)
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(SowAnnotatedImage::withTrashed()->findOrFail($image->id)->trashed())->toBeTrue();
});

it('stops a supervisor deleting someone else’s photo in a draft SOW', function (): void {
    $fixture = sowDeleteFixture();
    $report = sowDraftReport($this, $fixture['supervisor'], $fixture['market'], $fixture['project'], 'Draft Foreign Photo');
    $image = sowAttachPhoto($report, sowReadyAsset($fixture['manager'], $fixture['market']));

    sowDeletePhoto($this, $fixture['supervisor'], $report, $image)->assertForbidden();

    expect(SowAnnotatedImage::findOrFail($image->id)->trashed())->toBeFalse();
});

it('stops a supervisor deleting their own photo once submitted for review', function (): void {
    $fixture = sowDeleteFixture();
    $report = sowDraftReport($this, $fixture['supervisor'], $fixture['market'], $fixture['project'], 'Review Own Photo');
    $image = sowAttachPhoto($report, sowReadyAsset($fixture['supervisor'], $fixture['market']));

    $this->actingAs($fixture['supervisor'])->post(route('sow.reports.submit', $report));

    sowDeletePhoto($this, $fixture['supervisor'], $report->refresh(), $image)->assertForbidden();

    expect(SowAnnotatedImage::findOrFail($image->id)->trashed())->toBeFalse();
});

it('lets a manager delete a supervisor’s photo in an in-review SOW', function (): void {
    $fixture = sowDeleteFixture();
    $report = sowDraftReport($this, $fixture['supervisor'], $fixture['market'], $fixture['project'], 'Review Team Photo');
    $image = sowAttachPhoto($report, sowReadyAsset($fixture['supervisor'], $fixture['market']));

    $this->actingAs($fixture['supervisor'])->post(route('sow.reports.submit', $report));

    sowDeletePhoto($this, $fixture['manager'], $report->refresh(), $image)
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(SowAnnotatedImage::withTrashed()->findOrFail($image->id)->trashed())->toBeTrue();
});

it('stops everyone deleting photos from a published SOW', function (): void {
    $fixture = sowDeleteFixture();
    $report = sowDraftReport($this, $fixture['supervisor'], $fixture['market'], $fixture['project'], 'Published Photo');
    $image = sowAttachPhoto($report, sowReadyAsset($fixture['supervisor'], $fixture['market']));
    $report->update(['status' => SowStatus::PUBLISHED]);

    sowDeletePhoto($this, $fixture['manager'], $report->refresh(), $image)->assertForbidden();
    sowDeletePhoto($this, $fixture['admin'], $report->refresh(), $image)->assertForbidden();

    expect(SowAnnotatedImage::findOrFail($image->id)->trashed())->toBeFalse();
});

it('lets an admin delete any photo in an in-review SOW', function (): void {
    $fixture = sowDeleteFixture();
    $report = sowDraftReport($this, $fixture['supervisor'], $fixture['market'], $fixture['project'], 'Admin Delete Photo');
    $image = sowAttachPhoto($report, sowReadyAsset($fixture['supervisor'], $fixture['market']));

    $this->actingAs($fixture['supervisor'])->post(route('sow.reports.submit', $report));

    sowDeletePhoto($this, $fixture['admin'], $report->refresh(), $image)
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('rejects a photo id from another report', function (): void {
    $fixture = sowDeleteFixture();
    $reportA = sowDraftReport($this, $fixture['supervisor'], $fixture['market'], $fixture['project'], 'Report A Photo');
    $reportB = sowDraftReport($this, $fixture['supervisor'], $fixture['market'], $fixture['project'], 'Report B Photo');
    $imageB = sowAttachPhoto($reportB, sowReadyAsset($fixture['supervisor'], $fixture['market']));

    sowDeletePhoto($this, $fixture['manager'], $reportA, $imageB)->assertForbidden();

    expect(SowAnnotatedImage::findOrFail($imageB->id)->trashed())->toBeFalse();
});
