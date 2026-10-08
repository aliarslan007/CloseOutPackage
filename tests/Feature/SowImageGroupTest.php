<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Markets\Models\Market;
use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\PdfGeneration\Services\BrowsershotPdfService;
use App\Domains\Projects\Models\Project;
use App\Domains\ScopeOfWork\Enums\CompositeStatus;
use App\Domains\ScopeOfWork\Enums\SowStatus;
use App\Domains\ScopeOfWork\Models\SowAnnotatedImage;
use App\Domains\ScopeOfWork\Models\SowImageGroup;
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

/**
 * @return array{market: Market, supervisor: User, report: SowReport, images: list<SowAnnotatedImage>}
 */
function sowGroupFixture(int $imageCount = 3, array $reportAttributes = []): array
{
    $market = Market::factory()->create(['is_active' => true]);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);

    $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);

    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $supervisor->markets()->attach($market->id);

    $report = SowReport::create(array_merge([
        'market_id' => $market->id,
        'created_by' => $supervisor->id,
        'project_id' => $project->id,
        'status' => SowStatus::DRAFT,
        'title' => 'Grouping SOW',
        'site_name' => 'Grouping Site',
        'revision' => 1,
        'revision_date' => now()->toDateString(),
    ], $reportAttributes));

    $images = [];
    foreach (range(1, $imageCount) as $order) {
        $asset = MediaAsset::factory()->create([
            'user_id' => $supervisor->id,
            'market_id' => $market->id,
            'project_id' => $project->id,
            'status' => MediaAssetStatus::READY->value,
        ]);

        $images[] = SowAnnotatedImage::create([
            'sow_report_id' => $report->id,
            'media_asset_id' => $asset->id,
            'sort_order' => $order - 1,
            'annotation_layer' => [],
            'composite_status' => CompositeStatus::NOT_NEEDED,
            'include_in_pdf' => true,
        ]);
    }

    return compact('market', 'manager', 'project', 'supervisor', 'report', 'images');
}

it('creates groups appended in order', function (): void {
    extract(sowGroupFixture());

    $this->actingAs($supervisor)
        ->postJson(route('sow.image-groups.store', $report->id), ['name' => 'Site Plan'])
        ->assertOk()
        ->assertJsonPath('group.name', 'Site Plan')
        ->assertJsonPath('group.sort_order', 0);

    $this->actingAs($supervisor)
        ->postJson(route('sow.image-groups.store', $report->id), ['name' => 'Fiber Pathway'])
        ->assertOk()
        ->assertJsonPath('group.sort_order', 1);

    expect($report->imageGroups()->pluck('name')->all())->toBe(['Site Plan', 'Fiber Pathway']);
});

it('renames a group', function (): void {
    extract(sowGroupFixture());

    $group = $report->imageGroups()->create(['name' => 'Old Name', 'sort_order' => 0]);

    $this->actingAs($supervisor)
        ->putJson(route('sow.image-groups.update', [$report->id, $group->id]), ['name' => 'Fiber Pathway v2'])
        ->assertOk()
        ->assertJsonPath('group.name', 'Fiber Pathway v2');

    expect($group->fresh()->name)->toBe('Fiber Pathway v2');
});

it('rejects a duplicate group name within the same report', function (): void {
    extract(sowGroupFixture());

    $report->imageGroups()->create(['name' => 'Site Plan', 'sort_order' => 0]);

    $this->actingAs($supervisor)
        ->postJson(route('sow.image-groups.store', $report->id), ['name' => 'Site Plan'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('allows the same group name on a different report', function (): void {
    extract(sowGroupFixture());

    $otherReport = SowReport::create([
        'market_id' => $market->id,
        'created_by' => $supervisor->id,
        'status' => SowStatus::DRAFT,
        'title' => 'Other SOW',
    ]);

    $report->imageGroups()->create(['name' => 'Site Plan', 'sort_order' => 0]);

    $this->actingAs($supervisor)
        ->postJson(route('sow.image-groups.store', $otherReport->id), ['name' => 'Site Plan'])
        ->assertOk();
});

it('rejects the reserved ungrouped heading as a group name in any casing', function (string $name): void {
    extract(sowGroupFixture());

    $this->actingAs($supervisor)
        ->postJson(route('sow.image-groups.store', $report->id), ['name' => $name])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
})->with(['Other Photos', 'other photos', '  OTHER PHOTOS  ']);

it('rejects a group name longer than 120 characters', function (): void {
    extract(sowGroupFixture());

    $this->actingAs($supervisor)
        ->postJson(route('sow.image-groups.store', $report->id), ['name' => str_repeat('a', 121)])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('ungroups a group images when the group is deleted', function (): void {
    extract(sowGroupFixture());

    $group = $report->imageGroups()->create(['name' => 'Site Plan', 'sort_order' => 0]);
    $images[0]->update(['sow_image_group_id' => $group->id]);
    $images[1]->update(['sow_image_group_id' => $group->id]);

    $this->actingAs($supervisor)
        ->deleteJson(route('sow.image-groups.destroy', [$report->id, $group->id]))
        ->assertOk();

    expect(SowImageGroup::find($group->id))->toBeNull()
        ->and($images[0]->fresh()->sow_image_group_id)->toBeNull()
        ->and($images[1]->fresh()->sow_image_group_id)->toBeNull();
});

it('refuses to rename or delete a group belonging to another report', function (): void {
    extract(sowGroupFixture());

    $otherReport = SowReport::create([
        'market_id' => $market->id,
        'created_by' => $supervisor->id,
        'status' => SowStatus::DRAFT,
        'title' => 'Other SOW',
    ]);
    $foreignGroup = $otherReport->imageGroups()->create(['name' => 'Foreign', 'sort_order' => 0]);

    $this->actingAs($supervisor)
        ->putJson(route('sow.image-groups.update', [$report->id, $foreignGroup->id]), ['name' => 'Hijacked'])
        ->assertForbidden();

    $this->actingAs($supervisor)
        ->deleteJson(route('sow.image-groups.destroy', [$report->id, $foreignGroup->id]))
        ->assertForbidden();

    expect($foreignGroup->fresh()->name)->toBe('Foreign');
});

it('writes the whole layout atomically with contiguous global sort order', function (): void {
    extract(sowGroupFixture(4));

    $sitePlan = $report->imageGroups()->create(['name' => 'Site Plan', 'sort_order' => 0]);
    $fiber = $report->imageGroups()->create(['name' => 'Fiber Pathway', 'sort_order' => 1]);

    // Send one image deliberately ungrouped and out of order.
    $this->actingAs($supervisor)
        ->patchJson(route('sow.images.layout', $report->id), [
            'groups' => [
                ['id' => $fiber->id, 'sort_order' => 0],
                ['id' => $sitePlan->id, 'sort_order' => 1],
            ],
            'images' => [
                ['id' => $images[0]->id, 'sow_image_group_id' => $sitePlan->id, 'sort_order' => 0],
                ['id' => $images[1]->id, 'sow_image_group_id' => $sitePlan->id, 'sort_order' => 1],
                ['id' => $images[2]->id, 'sow_image_group_id' => $fiber->id, 'sort_order' => 0],
                ['id' => $images[3]->id, 'sow_image_group_id' => null, 'sort_order' => 0],
            ],
        ])
        ->assertOk();

    // Group order follows the payload, not creation order.
    expect($report->imageGroups()->pluck('name')->all())->toBe(['Fiber Pathway', 'Site Plan']);

    $ordered = $report->fresh()->annotatedImages()->get();

    // Global sort_order is contiguous 0..n-1, so the flat list is deterministic.
    expect($ordered->pluck('sort_order')->all())->toBe([0, 1, 2, 3])
        ->and($ordered->pluck('id')->all())->toBe([
            $images[2]->id, // Fiber Pathway first (group order)
            $images[0]->id,
            $images[1]->id,
            $images[3]->id, // ungrouped last
        ])
        ->and($images[3]->fresh()->sow_image_group_id)->toBeNull();
});

it('rejects layout payloads referencing another report images', function (): void {
    extract(sowGroupFixture(2));

    $otherReport = SowReport::create([
        'market_id' => $market->id,
        'created_by' => $supervisor->id,
        'status' => SowStatus::DRAFT,
        'title' => 'Other SOW',
    ]);
    $foreignAsset = MediaAsset::factory()->create([
        'user_id' => $supervisor->id,
        'market_id' => $market->id,
        'status' => MediaAssetStatus::READY->value,
    ]);
    $foreignImage = SowAnnotatedImage::create([
        'sow_report_id' => $otherReport->id,
        'media_asset_id' => $foreignAsset->id,
        'sort_order' => 0,
        'annotation_layer' => [],
        'composite_status' => CompositeStatus::NOT_NEEDED,
    ]);

    $this->actingAs($supervisor)
        ->patchJson(route('sow.images.layout', $report->id), [
            'groups' => [],
            'images' => [['id' => $foreignImage->id, 'sow_image_group_id' => null, 'sort_order' => 0]],
        ])
        ->assertStatus(422);

    expect($foreignImage->fresh()->sort_order)->toBe(0)
        ->and($images[0]->fresh()->sort_order)->toBe(0);
});

it('rejects a layout that points an image at a group from another report', function (): void {
    extract(sowGroupFixture(1));

    $otherReport = SowReport::create([
        'market_id' => $market->id,
        'created_by' => $supervisor->id,
        'status' => SowStatus::DRAFT,
        'title' => 'Other SOW',
    ]);
    $foreignGroup = $otherReport->imageGroups()->create(['name' => 'Foreign', 'sort_order' => 0]);

    $this->actingAs($supervisor)
        ->patchJson(route('sow.images.layout', $report->id), [
            'groups' => [],
            'images' => [
                ['id' => $images[0]->id, 'sow_image_group_id' => $foreignGroup->id, 'sort_order' => 0],
            ],
        ])
        ->assertStatus(422);

    expect($images[0]->fresh()->sow_image_group_id)->toBeNull();
});

it('leaves the layout untouched when the payload is invalid', function (): void {
    extract(sowGroupFixture(2));

    $group = $report->imageGroups()->create(['name' => 'Site Plan', 'sort_order' => 0]);
    $images[0]->update(['sow_image_group_id' => $group->id, 'sort_order' => 0]);
    $images[1]->update(['sort_order' => 1]);

    // Duplicate image ids — must be refused before any write happens.
    $this->actingAs($supervisor)
        ->patchJson(route('sow.images.layout', $report->id), [
            'groups' => [['id' => $group->id, 'sort_order' => 0]],
            'images' => [
                ['id' => $images[0]->id, 'sow_image_group_id' => null, 'sort_order' => 0],
                ['id' => $images[0]->id, 'sow_image_group_id' => null, 'sort_order' => 1],
            ],
        ])
        ->assertStatus(422);

    expect($images[0]->fresh()->sow_image_group_id)->toBe($group->id)
        ->and($images[0]->fresh()->sort_order)->toBe(0);
});

it('blocks grouping writes once the report is published', function (): void {
    extract(sowGroupFixture(1, ['status' => SowStatus::PUBLISHED]));

    $this->actingAs($supervisor)
        ->postJson(route('sow.image-groups.store', $report->id), ['name' => 'Site Plan'])
        ->assertForbidden();
});

it('keeps groups and memberships through submit, publish, and reopen', function (): void {
    extract(sowGroupFixture(3));

    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    $project->fieldSupervisors()->attach($supervisor->id);

    $sitePlan = $report->imageGroups()->create(['name' => 'Site Plan', 'sort_order' => 0]);
    $fiber = $report->imageGroups()->create(['name' => 'Fiber Pathway', 'sort_order' => 1]);

    $this->actingAs($supervisor)
        ->patchJson(route('sow.images.layout', $report->id), [
            'groups' => [
                ['id' => $sitePlan->id, 'sort_order' => 0],
                ['id' => $fiber->id, 'sort_order' => 1],
            ],
            'images' => [
                ['id' => $images[0]->id, 'sow_image_group_id' => $sitePlan->id, 'sort_order' => 0],
                ['id' => $images[1]->id, 'sow_image_group_id' => $sitePlan->id, 'sort_order' => 1],
                ['id' => $images[2]->id, 'sow_image_group_id' => $fiber->id, 'sort_order' => 0],
            ],
        ])
        ->assertOk();

    $this->actingAs($supervisor)->post(route('sow.reports.submit', $report->id))->assertRedirect();

    $this->mock(BrowsershotPdfService::class, function ($mock): void {
        $mock->shouldReceive('generate')->andReturn('%PDF-1.4 mock%');
    });

    $this->actingAs($manager)->post(route('sow.reports.publish', $report->id))->assertRedirect();
    $this->actingAs($manager)
        ->post(route('sow.reports.reopen', $report->id), ['notes' => 'Please regroup.'])
        ->assertRedirect();

    $fresh = $report->fresh();

    // Reopening must not disturb the grouping in any way.
    expect($fresh->status)->toBe(SowStatus::DRAFT)
        ->and($fresh->imageGroups()->pluck('name')->all())->toBe(['Site Plan', 'Fiber Pathway'])
        ->and($fresh->annotatedImages()->pluck('sow_image_group_id')->all())
        ->toBe([$sitePlan->id, $sitePlan->id, $fiber->id]);

    // …and the technician's page still receives it, so the board renders grouped.
    $this->withoutVite();
    $this->actingAs($supervisor)
        ->get(route('sow.reports.show', $report->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ScopeOfWork/Show')
            ->has('report.image_groups', 2)
            ->where('report.image_groups.0.name', 'Site Plan')
            ->where('report.image_groups.1.name', 'Fiber Pathway')
            ->where('report.annotated_images.0.sow_image_group_id', $sitePlan->id));
});

it('includes image_groups on the show payload', function (): void {
    $this->withoutVite();
    extract(sowGroupFixture(1));

    $report->imageGroups()->create(['name' => 'Site Plan', 'sort_order' => 0]);

    $this->actingAs($supervisor)
        ->get(route('sow.reports.show', $report->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ScopeOfWork/Show')
            ->has('report.image_groups', 1)
            ->where('report.image_groups.0.name', 'Site Plan'));
});
