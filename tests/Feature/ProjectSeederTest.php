<?php

use App\Domains\Projects\Enums\ProjectStatus;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\ProjectSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('re-points a stale seeded project creator at the demo admin', function (): void {
    $this->seed(AdminUserSeeder::class);

    $demoAdmin = User::where('email', 'testing@mail.com')->firstOrFail();

    $project = Project::factory()->create([
        'name' => 'Constitution Ave Small Cell 04',
        'status' => ProjectStatus::IN_PROGRESS,
        'progress_percentage' => 19,
        'created_by' => User::factory()->create()->id,
    ]);

    $this->seed(ProjectSeeder::class);

    $project->refresh();

    expect($project->created_by)->toBe($demoAdmin->id)
        ->and($project->progress_percentage)->toBe(19)
        ->and($project->status)->toBe(ProjectStatus::IN_PROGRESS);
});

it('leaves authorship untouched when the seeded project already credits the demo admin', function (): void {
    $this->seed(AdminUserSeeder::class);

    $demoAdmin = User::where('email', 'testing@mail.com')->firstOrFail();

    $project = Project::factory()->create([
        'name' => 'Columbus North Macro 5G',
        'created_by' => $demoAdmin->id,
    ]);

    $this->seed(ProjectSeeder::class);

    expect($project->refresh()->created_by)->toBe($demoAdmin->id);
});
