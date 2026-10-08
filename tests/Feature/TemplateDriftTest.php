<?php

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Enums\StageStatus;
use App\Domains\CloseoutPackages\Jobs\NotifyPackageDriftJob;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use App\Domains\CloseoutPackages\Models\PackageChecklistResponse;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\CloseoutPackages\Notifications\TemplateDriftNotification;
use App\Domains\Markets\Models\Market;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Actions\UpdateTemplatePictureCategoryAction;
use App\Domains\Templates\Enums\StageType;
use App\Domains\Templates\Models\ChecklistItem;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplatePictureCategory;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->fixture = function (): array {
        $market = Market::factory()->create();
        $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
        $supervisor->markets()->attach($market->id);
        $project = Project::factory()->create(['market_id' => $market->id]);
        $project->fieldSupervisors()->attach($supervisor->id);

        $template = Template::factory()->create(['market_id' => $market->id]);
        $section = $template->sections()->where('stage', StageType::PRE_CONSTRUCTION->value)->firstOrFail();

        foreach ($template->sections()->get() as $stageSection) {
            if ($stageSection->id !== $section->id) {
                TemplatePictureCategory::factory()->count(1)->create(['template_section_id' => $stageSection->id]);
            }
        }

        $categoryA = TemplatePictureCategory::factory()->create([
            'template_section_id' => $section->id,
            'default_picture_count' => 1,
        ]);
        $categoryB = TemplatePictureCategory::factory()->create([
            'template_section_id' => $section->id,
            'default_picture_count' => 1,
        ]);

        $this->actingAs($supervisor)
            ->post(route('packages.store', $project), [
                'project_id' => $project->id,
                'template_id' => $template->id,
            ])
            ->assertSessionHasNoErrors();

        $package = CloseoutPackage::firstOrFail();

        return compact('package', 'supervisor', 'template', 'section', 'categoryA', 'categoryB');
    };
});

it('blocks submitting a package when a template bucket shrank below uploaded photos', function (): void {
    $fixture = ($this->fixture)();
    $package = $fixture['package'];

    $selection = $package->stages()
        ->where('stage', StageType::PRE_CONSTRUCTION->value)
        ->firstOrFail()
        ->categorySelections()
        ->where('template_picture_category_id', $fixture['categoryA']->id)
        ->firstOrFail();

    $fixture['categoryA']->update(['default_picture_count' => 1]);

    Photo::factory()->count(2)->create([
        'closeout_package_id' => $package->id,
        'package_stage_id' => $selection->package_stage_id,
        'package_category_selection_id' => $selection->id,
    ]);

    $package->stages()->update([
        'status' => StageStatus::SUBMITTED->value,
        'submitted_at' => now(),
        'submitted_by' => $fixture['supervisor']->id,
    ]);

    $this->actingAs($fixture['supervisor'])
        ->post(route('packages.submit', $package))
        ->assertSessionHas('error', 'Template changes require your attention (1 conflict(s)) before submitting.');

    expect($package->fresh()->status)->toBe(PackageStatus::DRAFT);
});

it('syncs a changed template bucket onto the package on draft save', function (): void {
    $fixture = ($this->fixture)();
    $package = $fixture['package'];

    $fixture['categoryA']->update(['default_picture_count' => 10]);
    $fixture['categoryB']->update(['default_picture_count' => 10]);

    $stage = $package->stages()->where('stage', StageType::PRE_CONSTRUCTION->value)->firstOrFail();
    $selectionA = $stage->categorySelections()
        ->where('template_picture_category_id', $fixture['categoryA']->id)
        ->firstOrFail();
    $selectionB = $stage->categorySelections()
        ->where('template_picture_category_id', $fixture['categoryB']->id)
        ->firstOrFail();

    $this->actingAs($fixture['supervisor'])
        ->post(route('packages.stages.draft', [$package, StageType::PRE_CONSTRUCTION->value]), [
            'category_selections' => [[
                'category_id' => $fixture['categoryA']->id,
                'is_selected' => true,
                'picture_count' => 1,
            ]],
        ])
        ->assertSessionHasNoErrors();

    // The live template wins over the stale client value, on sent and unsent rows alike.
    expect($selectionA->fresh()->picture_count)->toBe(10)
        ->and($selectionB->fresh()->picture_count)->toBe(10);
});

it('heals a changed template bucket when the package page is opened', function (): void {
    $fixture = ($this->fixture)();
    $package = $fixture['package'];

    $stage = $package->stages()->where('stage', StageType::PRE_CONSTRUCTION->value)->firstOrFail();
    $selection = $stage->categorySelections()
        ->where('template_picture_category_id', $fixture['categoryA']->id)
        ->firstOrFail();

    $selection->update(['picture_count' => 12]);
    $fixture['categoryA']->update(['default_picture_count' => 1]);

    $this->actingAs($fixture['supervisor'])
        ->get(route('packages.show', $package))
        ->assertOk();

    expect($selection->fresh()->picture_count)->toBe(1);
});

it('adopts a raised template bucket when the package page is opened', function (): void {
    $fixture = ($this->fixture)();
    $package = $fixture['package'];

    $stage = $package->stages()->where('stage', StageType::PRE_CONSTRUCTION->value)->firstOrFail();
    $selection = $stage->categorySelections()
        ->where('template_picture_category_id', $fixture['categoryA']->id)
        ->firstOrFail();
    $fixture['categoryA']->update(['default_picture_count' => 10]);

    $this->actingAs($fixture['supervisor'])
        ->get(route('packages.show', $package))
        ->assertOk();

    expect($selection->fresh()->picture_count)->toBe(10);
});

it('clears stale explanation text on draft save', function (): void {
    $fixture = ($this->fixture)();
    $package = $fixture['package'];

    $item = ChecklistItem::factory()->create([
        'template_id' => $fixture['template']->id,
        'has_explain_field' => false,
    ]);
    $response = PackageChecklistResponse::factory()->create([
        'closeout_package_id' => $package->id,
        'checklist_item_id' => $item->id,
        'answer' => false,
        'explain' => 'Legacy explanation',
    ]);

    $this->actingAs($fixture['supervisor'])
        ->post(route('packages.stages.draft', [$package, StageType::PRE_CONSTRUCTION->value]), [
            'category_selections' => [[
                'category_id' => $fixture['categoryA']->id,
                'is_selected' => true,
                'picture_count' => 1,
            ]],
        ])
        ->assertSessionHasNoErrors();

    expect($response->fresh()->explain)->toBeNull();
});

it('re-selects a category the template now makes required on draft save', function (): void {
    $fixture = ($this->fixture)();
    $package = $fixture['package'];

    $stage = $package->stages()->where('stage', StageType::PRE_CONSTRUCTION->value)->firstOrFail();
    $selection = $stage->categorySelections()
        ->where('template_picture_category_id', $fixture['categoryA']->id)
        ->firstOrFail();

    $selection->update(['is_selected' => false]);
    $fixture['categoryA']->update(['is_required' => true]);

    $this->actingAs($fixture['supervisor'])
        ->post(route('packages.stages.draft', [$package, StageType::PRE_CONSTRUCTION->value]), [
            'category_selections' => [[
                'category_id' => $fixture['categoryB']->id,
                'is_selected' => true,
                'picture_count' => 1,
            ]],
        ])
        ->assertSessionHasNoErrors();

    expect($selection->fresh()->is_selected)->toBeTrue();
});

it('does not dispatch any job when a category becomes required', function (): void {
    Queue::fake();

    $template = Template::factory()->create();
    $section = $template->sections()->where('stage', StageType::PRE_CONSTRUCTION->value)->firstOrFail();
    $category = TemplatePictureCategory::factory()->create([
        'template_section_id' => $section->id,
        'default_picture_count' => 1,
        'is_required' => false,
    ]);

    CloseoutPackage::factory()->create([
        'template_id' => $template->id,
        'status' => PackageStatus::DRAFT,
    ]);

    app(UpdateTemplatePictureCategoryAction::class)->execute($category, $category->name, null, 1, true);

    Queue::assertNothingPushed();
});

it('blocks submitting a stage when the bucket shrank below uploaded photos', function (): void {
    $fixture = ($this->fixture)();
    $package = $fixture['package'];

    $stage = $package->stages()->where('stage', StageType::PRE_CONSTRUCTION->value)->firstOrFail();
    $selection = $stage->categorySelections()
        ->where('template_picture_category_id', $fixture['categoryA']->id)
        ->firstOrFail();

    $fixture['categoryA']->update(['default_picture_count' => 1]);

    Photo::factory()->count(2)->create([
        'closeout_package_id' => $package->id,
        'package_stage_id' => $stage->id,
        'package_category_selection_id' => $selection->id,
    ]);

    $this->actingAs($fixture['supervisor'])
        ->post(route('packages.stages.draft', [$package, StageType::PRE_CONSTRUCTION->value]), [
            'category_selections' => [[
                'category_id' => $fixture['categoryA']->id,
                'is_selected' => true,
                'picture_count' => 1,
            ]],
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($fixture['supervisor'])
        ->post(route('packages.stages.submit', [$package, StageType::PRE_CONSTRUCTION->value]))
        ->assertSessionHas('error', 'Template changes require your attention (1 conflict(s)) before submitting.');

    expect($stage->fresh()->status)->not->toBe(StageStatus::SUBMITTED);
});

it('blocks submitting a stage when a category was made required without a photo', function (): void {
    $fixture = ($this->fixture)();
    $package = $fixture['package'];

    $stage = $package->stages()->where('stage', StageType::PRE_CONSTRUCTION->value)->firstOrFail();

    // Optional at init (no photos), then the admin makes it required.
    $fixture['categoryA']->update(['is_required' => true]);

    $this->actingAs($fixture['supervisor'])
        ->post(route('packages.stages.draft', [$package, StageType::PRE_CONSTRUCTION->value]), [
            'category_selections' => [[
                'category_id' => $fixture['categoryA']->id,
                'is_selected' => true,
                'picture_count' => 1,
            ]],
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($fixture['supervisor'])
        ->post(route('packages.stages.submit', [$package, StageType::PRE_CONSTRUCTION->value]))
        ->assertSessionHas('error', "Add at least one photo to the required categories before submitting: \"{$fixture['categoryA']->name}\".");

    expect($stage->fresh()->status)->not->toBe(StageStatus::SUBMITTED);
});

it('submits a stage once the newly required category has a photo', function (): void {
    $fixture = ($this->fixture)();
    $package = $fixture['package'];

    $stage = $package->stages()->where('stage', StageType::PRE_CONSTRUCTION->value)->firstOrFail();
    $selection = $stage->categorySelections()
        ->where('template_picture_category_id', $fixture['categoryA']->id)
        ->firstOrFail();
    $fixture['categoryA']->update(['is_required' => true]);

    Photo::factory()->create([
        'closeout_package_id' => $package->id,
        'package_stage_id' => $stage->id,
        'package_category_selection_id' => $selection->id,
        'position' => 1,
    ]);

    $this->actingAs($fixture['supervisor'])
        ->post(route('packages.stages.draft', [$package, StageType::PRE_CONSTRUCTION->value]), [
            'category_selections' => [[
                'category_id' => $fixture['categoryA']->id,
                'is_selected' => true,
                'picture_count' => 1,
            ]],
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($fixture['supervisor'])
        ->post(route('packages.stages.submit', [$package, StageType::PRE_CONSTRUCTION->value]))
        ->assertSessionHas('success');

    expect($stage->fresh()->status)->toBe(StageStatus::SUBMITTED);
});

it('blocks submitting the package when a required category has no photo', function (): void {
    $fixture = ($this->fixture)();
    $package = $fixture['package'];

    $fixture['categoryA']->update(['is_required' => true]);

    $package->stages()->update([
        'status' => StageStatus::SUBMITTED->value,
        'submitted_at' => now(),
        'submitted_by' => $fixture['supervisor']->id,
    ]);

    $this->actingAs($fixture['supervisor'])
        ->post(route('packages.submit', $package))
        ->assertSessionHas('error', "Add at least one photo to the required categories before submitting: \"{$fixture['categoryA']->name}\".");

    expect($package->fresh()->status)->toBe(PackageStatus::DRAFT);
});

it('includes the drift prop on the package show page', function (): void {
    $fixture = ($this->fixture)();

    $this->actingAs($fixture['supervisor'])
        ->get(route('packages.show', $fixture['package']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Packages/Show')
            ->has('drift')
            ->has('drift.conflicts'));
});

it('dispatches one drift job per affected draft package on a bucket change', function (): void {
    Queue::fake();

    $template = Template::factory()->create();
    $section = $template->sections()->where('stage', StageType::PRE_CONSTRUCTION->value)->firstOrFail();
    $category = TemplatePictureCategory::factory()->create([
        'template_section_id' => $section->id,
        'default_picture_count' => 1,
    ]);

    CloseoutPackage::factory()->count(2)->create([
        'template_id' => $template->id,
        'status' => PackageStatus::DRAFT,
    ]);
    CloseoutPackage::factory()->create([
        'template_id' => $template->id,
        'status' => PackageStatus::APPROVED,
    ]);

    app(UpdateTemplatePictureCategoryAction::class)->execute($category, $category->name, null, 5, false);

    Queue::assertPushed(NotifyPackageDriftJob::class, 2);
});

it('does not dispatch any job for a heading-only change', function (): void {
    Queue::fake();

    $template = Template::factory()->create();
    $section = $template->sections()->where('stage', StageType::PRE_CONSTRUCTION->value)->firstOrFail();
    $category = TemplatePictureCategory::factory()->create([
        'template_section_id' => $section->id,
        'default_picture_count' => 1,
    ]);

    CloseoutPackage::factory()->create([
        'template_id' => $template->id,
        'status' => PackageStatus::DRAFT,
    ]);

    app(UpdateTemplatePictureCategoryAction::class)->execute($category, 'Renamed heading', null, 1, false);

    Queue::assertNothingPushed();
});

it('notifies the creator from the drift job when unsafe drift remains', function (): void {
    Notification::fake();

    $package = CloseoutPackage::factory()->create(['status' => PackageStatus::DRAFT]);
    $stage = PackageStage::factory()->create(['closeout_package_id' => $package->id]);
    $category = TemplatePictureCategory::factory()->create(['default_picture_count' => 1]);
    $selection = PackageCategorySelection::factory()->create([
        'package_stage_id' => $stage->id,
        'template_picture_category_id' => $category->id,
        'picture_count' => 5,
    ]);

    Photo::factory()->count(3)->create([
        'closeout_package_id' => $package->id,
        'package_stage_id' => $stage->id,
        'package_category_selection_id' => $selection->id,
    ]);

    (new NotifyPackageDriftJob($package->id))->handle();

    Notification::assertSentTo($package->creator, TemplateDriftNotification::class);
});

it('skips the drift notification for a package that is no longer a draft', function (): void {
    Notification::fake();

    $package = CloseoutPackage::factory()->create(['status' => PackageStatus::APPROVED]);

    (new NotifyPackageDriftJob($package->id))->handle();

    Notification::assertNothingSent();
});

it('skips the drift notification for a deleted package', function (): void {
    Notification::fake();

    (new NotifyPackageDriftJob(999999))->handle();

    Notification::assertNothingSent();
});
