<?php

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Enums\StageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Markets\Models\Market;
use App\Domains\Photos\Enums\PhotoStatus;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Models\ChecklistItem;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplatePictureCategory;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Storage::fake('private');

    $this->fixture = function (): array {
        $market = Market::factory()->create();
        $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
        $supervisor->markets()->attach($market->id);
        $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
        $manager->markets()->attach($market->id);
        $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
        $project->fieldSupervisors()->attach($supervisor->id);

        $template = Template::factory()->create(['market_id' => $market->id]);

        foreach ($template->sections()->get() as $section) {
            TemplatePictureCategory::factory()->count(1)->create(['template_section_id' => $section->id]);
        }

        ChecklistItem::factory()->count(2)->create(['template_id' => $template->id]);

        $response = $this->actingAs($supervisor)
            ->post(route('packages.store', $project), [
                'project_id' => $project->id,
                'template_id' => $template->id,
            ]);
        dump('store status: '.$response->getStatusCode());
        if ($response->getStatusCode() === 500) {
            preg_match('/<h1>(.*?)<\/h1>/s', $response->getContent(), $m);
            dump('500 title: '.($m[1] ?? 'none'));
            preg_match('/<pre.*?>(.*?)<\/pre>/s', $response->getContent(), $m2);
            dump('500 detail: '.substr(strip_tags($m2[1] ?? ''), 0, 400));
        }
        $response->assertSessionHasNoErrors();

        $package = CloseoutPackage::firstOrFail();

        return compact('package', 'supervisor', 'manager', 'template');
    };

    $this->submittedPackage = function (array $fixture): CloseoutPackage {
        $package = $fixture['package'];

        foreach ($package->stages()->get() as $stage) {
            $stage->update(['status' => StageStatus::SUBMITTED]);
        }

        $package->update(['status' => PackageStatus::SUBMITTED]);

        return $package;
    };
});

it('blocks photo uploads once the package is submitted', function (): void {
    $fixture = ($this->fixture)();
    $package = ($this->submittedPackage)($fixture);
    $stage = $package->stages()->where('stage', $package->current_stage)->firstOrFail();
    $selection = $stage->categorySelections()->firstOrFail();

    $this->actingAs($fixture['supervisor'])
        ->post(route('photos.store', [$package, $package->current_stage->value]), [
            'photo' => UploadedFile::fake()->image('photo.jpg'),
            'category_selection_id' => $selection->id,
            'position' => 1,
        ])
        ->assertSessionHas('error', 'This package has been submitted. Only flagged photos can be replaced.');
});

it('blocks draft saves once the package is submitted', function (): void {
    $fixture = ($this->fixture)();
    $package = ($this->submittedPackage)($fixture);
    $template = $package->template()->with('sections.categories')->firstOrFail();
    $categoryIds = $template->sections()
        ->where('stage', $package->current_stage)
        ->firstOrFail()->categories()->pluck('id')->all();

    $this->actingAs($fixture['supervisor'])
        ->post(route('packages.stages.draft', [$package, $package->current_stage->value]), [
            'category_selections' => array_map(
                fn (int $id): array => ['category_id' => $id, 'is_selected' => true, 'picture_count' => 1],
                $categoryIds,
            ),
        ])
        ->assertSessionHas('error', 'This package has been submitted and can no longer be edited.');
});

it('blocks checklist updates once the package is submitted', function (): void {
    $fixture = ($this->fixture)();
    $package = ($this->submittedPackage)($fixture);

    $this->actingAs($fixture['supervisor'])
        ->put(route('packages.checklist.update', $package), [
            'responses' => [[
                'checklist_item_id' => $fixture['template']->checklistItems()->firstOrFail()->id,
                'answer' => true,
            ]],
        ])
        ->assertSessionHas('error', 'This package has been submitted and its checklist can no longer be changed.');
});

it('blocks re-submitting an already submitted package', function (): void {
    $fixture = ($this->fixture)();
    $package = ($this->submittedPackage)($fixture);

    $this->actingAs($fixture['supervisor'])
        ->post(route('packages.submit', $package))
        ->assertSessionHas('error', 'Only draft packages can be submitted.');
});

it('blocks package submission until all stages are submitted', function (): void {
    $fixture = ($this->fixture)();
    $package = $fixture['package'];

    $this->actingAs($fixture['supervisor'])
        ->post(route('packages.submit', $package))
        ->assertSessionHas('error', 'Submit all stages before submitting the package.');
});

it('allows replacing a flagged photo after the package is rejected', function (): void {
    $fixture = ($this->fixture)();
    $package = ($this->submittedPackage)($fixture);
    $stage = $package->stages()->where('stage', $package->current_stage)->firstOrFail();
    $selection = $stage->categorySelections()->firstOrFail();
    $photo = Photo::factory()->create([
        'closeout_package_id' => $package->id,
        'package_stage_id' => $selection->package_stage_id,
        'package_category_selection_id' => $selection->id,
        'position' => 1,
        'status' => PhotoStatus::FLAGGED,
    ]);
    $package->update(['status' => PackageStatus::REJECTED]);

    $this->actingAs($fixture['supervisor'])
        ->post(route('photos.store', [$package, $package->current_stage->value]), [
            'photo' => UploadedFile::fake()->image('replacement.jpg'),
            'category_selection_id' => $selection->id,
            'position' => 1,
        ])
        ->assertSessionHas('success');

    expect($photo->fresh()->replaced_at)->not->toBeNull();
});

it('marks a submitted package as under review when the manager opens it', function (): void {
    $fixture = ($this->fixture)();
    $package = ($this->submittedPackage)($fixture);

    $this->actingAs($fixture['manager'])
        ->get(route('packages.show', $package))
        ->assertOk();

    expect($package->fresh()->status)->toBe(PackageStatus::UNDER_REVIEW);
});

it('does not change the status when a non-submitted package is opened', function (): void {
    $fixture = ($this->fixture)();
    $package = $fixture['package'];

    $this->actingAs($fixture['manager'])
        ->get(route('packages.show', $package))
        ->assertOk();

    expect($package->fresh()->status)->toBe(PackageStatus::DRAFT);
});
