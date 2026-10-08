<?php

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Enums\StageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Enums\StageType;
use App\Domains\Templates\Models\ChecklistItem;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplatePictureCategory;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        foreach ($template->sections()->get() as $section) {
            TemplatePictureCategory::factory()->count(1)->create(['template_section_id' => $section->id]);
        }

        ChecklistItem::factory()->count(2)->create(['template_id' => $template->id]);
        ChecklistItem::factory()->create([
            'template_id' => $template->id,
            'has_explain_field' => true,
        ]);

        $this->actingAs($supervisor)
            ->post(route('packages.store', $project), [
                'project_id' => $project->id,
                'template_id' => $template->id,
            ])
            ->assertSessionHasNoErrors();

        $package = CloseoutPackage::firstOrFail();

        $package->update(['current_stage' => StageType::POST_COMPLETION]);

        return compact('package', 'supervisor', 'template');
    };

    $this->draft = function (CloseoutPackage $package, User $actor, array $checklistResponses): void {
        $template = $package->template()->with('sections.categories')->firstOrFail();
        $categoryIds = $template->sections()->where('stage', StageType::POST_COMPLETION->value)
            ->firstOrFail()->categories()->pluck('id')->all();

        $url = route('packages.stages.draft', [$package, StageType::POST_COMPLETION->value]);
        $response = $this->actingAs($actor)->post($url, [
            'category_selections' => array_map(fn (int $id): array => [
                'category_id' => $id,
                'is_selected' => true,
                'picture_count' => 1,
            ], $categoryIds),
            'checklist_responses' => $checklistResponses,
        ]);
        $response->assertSessionHasNoErrors();
    };

    $this->submitFinal = function (CloseoutPackage $package, User $actor) {
        $package->stages()
            ->where('stage', '!=', StageType::POST_COMPLETION->value)
            ->update([
                'status' => StageStatus::SUBMITTED->value,
                'submitted_at' => now(),
                'submitted_by' => $actor->id,
            ]);

        $this->actingAs($actor)
            ->post(route('packages.stages.submit', [$package, StageType::POST_COMPLETION->value]))
            ->assertRedirect()
            ->assertSessionHas('success');

        return $this->actingAs($actor)
            ->post(route('packages.submit', $package));
    };
});

it('blocks final submit when checklist items are unanswered', function (): void {
    $fixture = ($this->fixture)();

    ($this->draft)($fixture['package'], $fixture['supervisor'], []);

    $response = ($this->submitFinal)($fixture['package'], $fixture['supervisor']);
    $response->assertSessionHas('error', 'Please answer all checklist items before submitting the package.');

    expect($fixture['package']->fresh())
        ->status->toBe(PackageStatus::DRAFT)
        ->submitted_at->toBeNull();
});

it('rejects answer notes longer than the character limit', function (): void {
    $fixture = ($this->fixture)();

    $this->actingAs($fixture['supervisor'])
        ->put(route('packages.checklist.update', $fixture['package']), [
            'responses' => [[
                'checklist_item_id' => $fixture['template']->checklistItems[2]->id,
                'answer' => true,
                'explain' => str_repeat('a', 1001),
            ]],
        ])
        ->assertSessionHasErrors('responses.0.explain');
});

it('submits when a No answer has no note because notes are optional', function (): void {
    $fixture = ($this->fixture)();

    ($this->draft)($fixture['package'], $fixture['supervisor'], [
        ['checklist_item_id' => $fixture['template']->checklistItems[0]->id, 'answer' => true],
        ['checklist_item_id' => $fixture['template']->checklistItems[1]->id, 'answer' => true],
        ['checklist_item_id' => $fixture['template']->checklistItems[2]->id, 'answer' => false, 'explain' => null],
    ]);

    ($this->submitFinal)($fixture['package'], $fixture['supervisor'])
        ->assertSessionHas('success');

    expect($fixture['package']->fresh())->status->toBe(PackageStatus::SUBMITTED);
});

it('submits the package when the checklist is complete', function (): void {
    $fixture = ($this->fixture)();

    ($this->draft)($fixture['package'], $fixture['supervisor'], $fixture['template']->checklistItems->map(
        fn (ChecklistItem $item): array => [
            'checklist_item_id' => $item->id,
            'answer' => $item->has_explain_field ? false : true,
            'explain' => $item->has_explain_field ? 'Not applicable on this site.' : null,
        ],
    )->all());

    ($this->submitFinal)($fixture['package'], $fixture['supervisor'])
        ->assertSessionHas('success');

    expect($fixture['package']->fresh())
        ->status->toBe(PackageStatus::SUBMITTED)
        ->submitted_at->not->toBeNull();
});

it('keeps a submitted stage submitted when the submit chain saves a draft first', function (): void {
    $fixture = ($this->fixture)();
    $package = $fixture['package'];
    $answers = $fixture['template']->checklistItems->map(
        fn (ChecklistItem $item): array => [
            'checklist_item_id' => $item->id,
            'answer' => $item->has_explain_field ? false : true,
            'explain' => $item->has_explain_field ? 'Not applicable on this site.' : null,
        ],
    )->all();

    ($this->draft)($fixture['package'], $fixture['supervisor'], $answers);

    $this->actingAs($fixture['supervisor'])
        ->post(route('packages.stages.submit', [$package, StageType::POST_COMPLETION->value]))
        ->assertSessionHas('success');

    expect($package->stages()->where('stage', StageType::POST_COMPLETION->value)->firstOrFail()->status)
        ->toBe(StageStatus::SUBMITTED);

    ($this->draft)($fixture['package'], $fixture['supervisor'], $answers);

    expect($package->stages()->where('stage', StageType::POST_COMPLETION->value)->firstOrFail()->status)
        ->toBe(StageStatus::SUBMITTED);

    $package->stages()
        ->where('stage', '!=', StageType::POST_COMPLETION->value)
        ->update([
            'status' => StageStatus::SUBMITTED->value,
            'submitted_at' => now(),
            'submitted_by' => $fixture['supervisor']->id,
        ]);

    $this->actingAs($fixture['supervisor'])
        ->post(route('packages.submit', $package))
        ->assertSessionHas('success');

    expect($package->fresh()->status)->toBe(PackageStatus::SUBMITTED);
});

it('dynamically syncs checklist items added after package initialization and enforces them on submission', function (): void {
    $market = Market::factory()->create();
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $supervisor->markets()->attach($market->id);
    $project = Project::factory()->create(['market_id' => $market->id]);
    $project->fieldSupervisors()->attach($supervisor->id);

    // Template starts with 0 checklist items
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
    expect($package->checklistResponses()->count())->toBe(0);

    // Admin adds a checklist item after package was initialized
    $newChecklistItem = ChecklistItem::factory()->create([
        'template_id' => $template->id,
        'label' => 'Are all safety guards in place?',
        'has_explain_field' => false,
    ]);

    // When supervisor opens the package page, the new checklist item is dynamically synchronized
    $this->actingAs($supervisor)
        ->get(route('packages.show', $package))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Packages/Show')
            ->has('package.checklist_responses', 1)
            ->where('package.checklist_responses.0.checklist_item_id', $newChecklistItem->id)
            ->where('package.checklist_responses.0.answer', null)
        );

    expect($package->fresh()->checklistResponses()->count())->toBe(1);

    // Mark stages as submitted
    $package->stages()->update([
        'status' => StageStatus::SUBMITTED->value,
        'submitted_at' => now(),
        'submitted_by' => $supervisor->id,
    ]);

    // Submitting package is blocked because the new checklist item is not answered yet
    $this->actingAs($supervisor)
        ->post(route('packages.submit', $package))
        ->assertSessionHas('error', 'Please answer all checklist items before submitting the package.');

    expect($package->fresh()->status)->toBe(PackageStatus::DRAFT);

    // Supervisor answers the newly added checklist item via checklist update endpoint
    $this->actingAs($supervisor)
        ->put(route('packages.checklist.update', $package), [
            'responses' => [
                [
                    'checklist_item_id' => $newChecklistItem->id,
                    'answer' => true,
                    'explain' => null,
                ],
            ],
        ])
        ->assertSessionHasNoErrors();

    // Now final package submission succeeds
    $this->actingAs($supervisor)
        ->post(route('packages.submit', $package))
        ->assertSessionHas('success');

    expect($package->fresh()->status)->toBe(PackageStatus::SUBMITTED);
});

it('allows stages with no photo categories to be drafted and submitted without errors', function (): void {
    $market = Market::factory()->create();
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $supervisor->markets()->attach($market->id);
    $project = Project::factory()->create(['market_id' => $market->id]);
    $project->fieldSupervisors()->attach($supervisor->id);

    $template = Template::factory()->create(['market_id' => $market->id]);

    // Only add a category to PRE_CONSTRUCTION, leaving CONSTRUCTION and POST_COMPLETION with 0 categories
    $preSection = $template->sections()->where('stage', StageType::PRE_CONSTRUCTION->value)->firstOrFail();
    $categoryPre = TemplatePictureCategory::factory()->create([
        'template_section_id' => $preSection->id,
        'default_picture_count' => 1,
        'is_required' => false,
    ]);

    $this->actingAs($supervisor)
        ->post(route('packages.store', $project), [
            'project_id' => $project->id,
            'template_id' => $template->id,
        ])
        ->assertSessionHasNoErrors();

    $package = CloseoutPackage::firstOrFail();

    // 1. Pre-construction: draft and submit
    $this->actingAs($supervisor)
        ->post(route('packages.stages.draft', [$package, StageType::PRE_CONSTRUCTION->value]), [
            'category_selections' => [
                [
                    'category_id' => $categoryPre->id,
                    'is_selected' => false,
                    'picture_count' => 1,
                ],
            ],
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($supervisor)
        ->post(route('packages.stages.submit', [$package, StageType::PRE_CONSTRUCTION->value]))
        ->assertSessionHas('success');

    // 2. Construction (has 0 categories): draft with category_selections: [] does not fail
    $this->actingAs($supervisor)
        ->post(route('packages.stages.draft', [$package, StageType::CONSTRUCTION->value]), [
            'category_selections' => [],
        ])
        ->assertSessionHasNoErrors();

    // 3. Construction submit directly without category errors
    $this->actingAs($supervisor)
        ->post(route('packages.stages.submit', [$package, StageType::CONSTRUCTION->value]))
        ->assertSessionHas('success');

    // 4. Post-completion (has 0 categories): can submit directly even without drafting first
    $this->actingAs($supervisor)
        ->post(route('packages.stages.submit', [$package, StageType::POST_COMPLETION->value]))
        ->assertSessionHas('success');

    // All stages are submitted
    expect($package->stages()->where('status', StageStatus::SUBMITTED->value)->count())->toBe(3);

    // 5. Package submit succeeds
    $this->actingAs($supervisor)
        ->post(route('packages.submit', $package))
        ->assertSessionHas('success');

    expect($package->fresh()->status)->toBe(PackageStatus::SUBMITTED);
});

it('does not block submission when a No answer has no note', function (): void {
    $market = Market::factory()->create();
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $supervisor->markets()->attach($market->id);
    $project = Project::factory()->create(['market_id' => $market->id]);
    $project->fieldSupervisors()->attach($supervisor->id);

    $template = Template::factory()->create(['market_id' => $market->id]);
    foreach ($template->sections()->get() as $section) {
        TemplatePictureCategory::factory()->count(1)->create(['template_section_id' => $section->id]);
    }

    $item = ChecklistItem::factory()->create([
        'template_id' => $template->id,
        'label' => 'Are permits posted on site?',
        'has_explain_field' => false,
    ]);

    $this->actingAs($supervisor)
        ->post(route('packages.store', $project), [
            'project_id' => $project->id,
            'template_id' => $template->id,
        ])
        ->assertSessionHasNoErrors();

    $package = CloseoutPackage::firstOrFail();

    // Tech answers No without explanation (because has_explain_field was false)
    $this->actingAs($supervisor)
        ->put(route('packages.checklist.update', $package), [
            'responses' => [
                [
                    'checklist_item_id' => $item->id,
                    'answer' => false,
                    'explain' => null,
                ],
            ],
        ])
        ->assertSessionHasNoErrors();

    // Mark stages as submitted
    $package->stages()->update([
        'status' => StageStatus::SUBMITTED->value,
        'submitted_at' => now(),
        'submitted_by' => $supervisor->id,
    ]);

    // Admin enables has_explain_field on the checklist item
    $item->update(['has_explain_field' => true]);

    // Notes are optional — submission succeeds without an explanation
    $this->actingAs($supervisor)
        ->post(route('packages.submit', $package))
        ->assertSessionHas('success');

    expect($package->fresh()->status)->toBe(PackageStatus::SUBMITTED);
});

it('bounds technician to answer when admin disables allows_na on an item previously marked NA', function (): void {
    $market = Market::factory()->create();
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $supervisor->markets()->attach($market->id);
    $project = Project::factory()->create(['market_id' => $market->id]);
    $project->fieldSupervisors()->attach($supervisor->id);

    $template = Template::factory()->create(['market_id' => $market->id]);
    foreach ($template->sections()->get() as $section) {
        TemplatePictureCategory::factory()->count(1)->create(['template_section_id' => $section->id]);
    }

    $item = ChecklistItem::factory()->create([
        'template_id' => $template->id,
        'label' => 'Subcontractor sign-off sheet',
        'allows_na' => true,
    ]);

    $this->actingAs($supervisor)
        ->post(route('packages.store', $project), [
            'project_id' => $project->id,
            'template_id' => $template->id,
        ])
        ->assertSessionHasNoErrors();

    $package = CloseoutPackage::firstOrFail();

    // Tech answers N/A
    $this->actingAs($supervisor)
        ->put(route('packages.checklist.update', $package), [
            'responses' => [
                [
                    'checklist_item_id' => $item->id,
                    'answer' => null,
                    'is_na' => true,
                    'explain' => null,
                ],
            ],
        ])
        ->assertSessionHasNoErrors();

    // Mark stages as submitted
    $package->stages()->update([
        'status' => StageStatus::SUBMITTED->value,
        'submitted_at' => now(),
        'submitted_by' => $supervisor->id,
    ]);

    // Admin disables allows_na on the template item
    $item->update(['allows_na' => false]);

    // Submitting package is blocked because N/A was reset and item requires answer
    $this->actingAs($supervisor)
        ->post(route('packages.submit', $package))
        ->assertSessionHas('error', 'Please answer all checklist items before submitting the package.');

    expect($package->fresh()->status)->toBe(PackageStatus::DRAFT);

    // Tech answers Yes
    $this->actingAs($supervisor)
        ->put(route('packages.checklist.update', $package), [
            'responses' => [
                [
                    'checklist_item_id' => $item->id,
                    'answer' => true,
                    'is_na' => false,
                    'explain' => null,
                ],
            ],
        ])
        ->assertSessionHasNoErrors();

    // Submission succeeds
    $this->actingAs($supervisor)
        ->post(route('packages.submit', $package))
        ->assertSessionHas('success');

    expect($package->fresh()->status)->toBe(PackageStatus::SUBMITTED);
});
