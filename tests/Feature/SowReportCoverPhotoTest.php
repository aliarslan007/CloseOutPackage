<?php

declare(strict_types=1);

use App\Domains\Markets\Models\Market;
use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\Projects\Models\Project;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Storage::fake('private');
});

it('exposes the project site photo url on the create page', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create(['market_id' => $market->id]);
    $project->addMedia(UploadedFile::fake()->image('site.jpg'))->toMediaCollection(Project::SITE_PHOTO_COLLECTION);

    $this->withoutVite();
    $this->actingAs($supervisor)
        ->get(route('sow.reports.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ScopeOfWork/Create')
            ->where('projects.0.site_photo_url', fn (?string $url): bool => is_string($url) && str_contains($url, 'site-photo')));
});

it('attaches the project site photo as cover when opted in', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create(['market_id' => $market->id]);
    $project->addMedia(UploadedFile::fake()->image('site.jpg'))->toMediaCollection(Project::SITE_PHOTO_COLLECTION);

    $this->actingAs($supervisor)
        ->post(route('sow.reports.store'), [
            'market_id' => $market->id,
            'project_id' => $project->id,
            'title' => 'SOW With Site Photo',
            'include_project_site_photo' => true,
        ])
        ->assertRedirect();

    $report = SowReport::where('title', 'SOW With Site Photo')->firstOrFail();
    $image = $report->annotatedImages()->firstOrFail();

    expect($image->is_cover)->toBeTrue()
        ->and($image->include_in_pdf)->toBeTrue()
        ->and($report->cover_media_asset_id)->toBe($image->media_asset_id);

    $asset = MediaAsset::findOrFail($image->media_asset_id);
    expect($asset->status)->toBe(MediaAssetStatus::READY)
        ->and($asset->market_id)->toBe($market->id)
        ->and($asset->project_id)->toBe($project->id);
});

it('skips the site photo when opted out', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create(['market_id' => $market->id]);
    $project->addMedia(UploadedFile::fake()->image('site.jpg'))->toMediaCollection(Project::SITE_PHOTO_COLLECTION);

    $this->actingAs($supervisor)
        ->post(route('sow.reports.store'), [
            'market_id' => $market->id,
            'project_id' => $project->id,
            'title' => 'SOW Without Site Photo',
            'include_project_site_photo' => false,
        ])
        ->assertRedirect();

    $report = SowReport::where('title', 'SOW Without Site Photo')->firstOrFail();
    expect($report->annotatedImages()->count())->toBe(0)
        ->and($report->cover_media_asset_id)->toBeNull();
});

it('attaches an explicit library cover pick instead of the site photo', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create(['market_id' => $market->id]);
    $project->addMedia(UploadedFile::fake()->image('site.jpg'))->toMediaCollection(Project::SITE_PHOTO_COLLECTION);
    $cover = MediaAsset::factory()->create([
        'user_id' => $supervisor->id,
        'market_id' => $market->id,
        'status' => MediaAssetStatus::READY,
    ]);

    $this->actingAs($supervisor)
        ->post(route('sow.reports.store'), [
            'market_id' => $market->id,
            'project_id' => $project->id,
            'title' => 'SOW With Library Cover',
            'cover_media_asset_id' => $cover->id,
            'include_project_site_photo' => true,
        ])
        ->assertRedirect();

    $report = SowReport::where('title', 'SOW With Library Cover')->firstOrFail();
    expect($report->annotatedImages()->count())->toBe(1)
        ->and($report->cover_media_asset_id)->toBe($cover->id);
});

it('rejects a cover photo from another market', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $otherMarket = Market::factory()->create(['is_active' => true]);
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create(['market_id' => $market->id]);
    $cover = MediaAsset::factory()->create([
        'user_id' => $supervisor->id,
        'market_id' => $otherMarket->id,
        'status' => MediaAssetStatus::READY,
    ]);

    $this->actingAs($supervisor)
        ->post(route('sow.reports.store'), [
            'market_id' => $market->id,
            'project_id' => $project->id,
            'title' => 'SOW With Foreign Cover',
            'cover_media_asset_id' => $cover->id,
        ])
        ->assertRedirect();

    expect(SowReport::where('title', 'SOW With Foreign Cover')->exists())->toBeFalse();
});
