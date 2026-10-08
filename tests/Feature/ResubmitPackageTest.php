<?php

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Markets\Models\Market;
use App\Domains\Photos\Enums\PhotoStatus;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplatePictureCategory;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        foreach ($template->sections()->get() as $section) {
            TemplatePictureCategory::factory()->count(1)->create(['template_section_id' => $section->id]);
        }

        $this->actingAs($supervisor)
            ->post(route('packages.store', $project), [
                'project_id' => $project->id,
                'template_id' => $template->id,
            ])
            ->assertSessionHasNoErrors();

        $package = CloseoutPackage::firstOrFail();
        $package->update(['status' => PackageStatus::REJECTED]);

        return compact('package', 'supervisor');
    };
});

it('blocks resubmitting a package that is not rejected', function (): void {
    $fixture = ($this->fixture)();
    $fixture['package']->update(['status' => PackageStatus::SUBMITTED]);

    $this->actingAs($fixture['supervisor'])
        ->post(route('packages.resubmit', $fixture['package']))
        ->assertSessionHas('error', 'Only rejected packages can be resubmitted.');
});

it('blocks resubmitting while flagged photos are still pending replacement', function (): void {
    $fixture = ($this->fixture)();
    $selection = $fixture['package']->stages()->firstOrFail()->categorySelections()->firstOrFail();
    Photo::create([
        'closeout_package_id' => $fixture['package']->id,
        'package_stage_id' => $selection->package_stage_id,
        'package_category_selection_id' => $selection->id,
        'uploaded_by' => $fixture['supervisor']->id,
        'position' => 1,
        'status' => PhotoStatus::FLAGGED,
    ]);

    $this->actingAs($fixture['supervisor'])
        ->post(route('packages.resubmit', $fixture['package']))
        ->assertSessionHas('error', 'Replace all flagged photos before resubmitting the package.');
});

it('resubmits a rejected package once flagged photos are replaced', function (): void {
    $fixture = ($this->fixture)();
    $selection = $fixture['package']->stages()->firstOrFail()->categorySelections()->firstOrFail();
    $photo = Photo::create([
        'closeout_package_id' => $fixture['package']->id,
        'package_stage_id' => $selection->package_stage_id,
        'package_category_selection_id' => $selection->id,
        'uploaded_by' => $fixture['supervisor']->id,
        'position' => 1,
        'status' => PhotoStatus::FLAGGED,
    ]);
    $photo->update(['replaced_at' => now()]);

    $this->actingAs($fixture['supervisor'])
        ->post(route('packages.resubmit', $fixture['package']))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($fixture['package']->fresh())
        ->status->toBe(PackageStatus::SUBMITTED)
        ->submitted_at->not->toBeNull();
});
