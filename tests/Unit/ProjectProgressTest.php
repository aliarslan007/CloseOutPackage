<?php

use App\Domains\Projects\Actions\UpdateProjectProgressAction;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Services\ProjectProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

it('reports zero progress while no packages exist', function (): void {
    $project = Project::factory()->create();

    expect(app(ProjectProgressService::class)->calculate($project))->toBe(0);
});

it('persists the calculated progress and clamps to the 0-100 range', function (): void {
    $project = Project::factory()->create(['progress_percentage' => 50]);

    app(UpdateProjectProgressAction::class)->execute($project);

    expect($project->fresh()->progress_percentage)->toBe(0);
});
