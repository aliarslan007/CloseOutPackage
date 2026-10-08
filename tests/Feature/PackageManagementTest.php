<?php

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Enums\StageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Markets\Models\Market;
use App\Domains\Photos\Enums\PhotoStatus;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Enums\StageType;
use App\Domains\Templates\Models\ChecklistItem;
use App\Domains\Templates\Models\FavoriteTemplate;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplatePictureCategory;
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

    $this->fixture = function (): array {
        $market = Market::factory()->create();
        $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
        $manager->markets()->attach($market->id);
        $project = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
        $template = Template::factory()->create(['market_id' => $market->id]);

        foreach ($template->sections()->get() as $section) {
            TemplatePictureCategory::factory()->count(2)->create(['template_section_id' => $section->id]);
        }

        ChecklistItem::factory()->count(2)->create(['template_id' => $template->id]);

        $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
        $field->markets()->attach($market->id);
        $project->fieldSupervisors()->attach($field->id);

        return compact('market', 'manager', 'project', 'template', 'field');
    };

    $this->draftPayload = function (Template $template, StageType $stage): array {
        $section = $template->sections()->where('stage', $stage)->firstOrFail();
        $categoryIds = $section->categories()->pluck('id')->all();

        return [
            'category_selections' => array_map(fn (int $id): array => [
                'category_id' => $id,
                'is_selected' => true,
                'picture_count' => 1,
            ], $categoryIds),
            'checklist_responses' => $template->checklistItems()->pluck('id')->map(fn (int $id): array => [
                'checklist_item_id' => $id,
                'answer' => true,
            ])->all(),
        ];
    };

    $this->submitStage = function (CloseoutPackage $package, StageType $stage, User $actor): void {
        $template = $package->template()->with('checklistItems')->firstOrFail();
        $payload = ($this->draftPayload)($template, $stage);

        $this->actingAs($actor)
            ->post(route('packages.stages.draft', [$package, $stage->value]), $payload)
            ->assertSessionHasNoErrors();

        $this->actingAs($actor)
            ->post(route('packages.stages.submit', [$package, $stage->value]))
            ->assertRedirect()
            ->assertSessionHas('success');
    };
});

it('denies admins from creating packages', function (): void {
    $fixture = ($this->fixture)();
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    $this->actingAs($admin)
        ->post(route('packages.store', $fixture['project']), [
            'project_id' => $fixture['project']->id,
            'template_id' => $fixture['template']->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');
});

it('creates a package and seeds stages, selections, and checklist responses', function (): void {
    $fixture = ($this->fixture)();
    $manager = $fixture['manager'];

    $this->actingAs($manager)
        ->post(route('packages.store', $fixture['project']), [
            'project_id' => $fixture['project']->id,
            'template_id' => $fixture['template']->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $package = CloseoutPackage::firstOrFail();

    expect($package)
        ->status->toBe(PackageStatus::DRAFT)
        ->revision_major->toBe(1)
        ->revision_minor->toBe(0)
        ->current_stage->toBe(StageType::PRE_CONSTRUCTION);

    expect($package->stages()->count())->toBe(3)
        ->and($package->stages()->where('status', StageStatus::NOT_STARTED->value)->count())->toBe(3);

    $siteInfo = $package->siteInfo()->firstOrFail();
    expect($siteInfo)
        ->site_name->toBe($fixture['project']->name)
        ->pm_name->toBe($fixture['manager']->name);

    $preStage = $package->stages()->where('stage', StageType::PRE_CONSTRUCTION->value)->firstOrFail();
    $section = $fixture['template']->sections()->where('stage', StageType::PRE_CONSTRUCTION->value)->firstOrFail();

    expect($preStage->categorySelections()->count())->toBe($section->categories()->count())
        ->and($package->checklistResponses()->count())->toBe($fixture['template']->checklistItems()->count());
});

it('rejects a template from a different market', function (): void {
    $fixture = ($this->fixture)();
    $manager = $fixture['manager'];
    $foreignTemplate = Template::factory()->create();

    $this->actingAs($manager)
        ->post(route('packages.store', $fixture['project']), [
            'project_id' => $fixture['project']->id,
            'template_id' => $foreignTemplate->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');
});

it('saves a draft with category selections and checklist responses', function (): void {
    $fixture = ($this->fixture)();
    $manager = $fixture['manager'];

    $this->actingAs($manager)->post(route('packages.store', $fixture['project']), [
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);

    $package = CloseoutPackage::firstOrFail();
    $stage = $package->stages()->where('stage', StageType::PRE_CONSTRUCTION->value)->firstOrFail();
    $selection = $stage->categorySelections()->firstOrFail();
    $selection->templatePictureCategory->update(['default_picture_count' => 3]);

    $this->actingAs($fixture['field'])
        ->post(route('packages.stages.draft', [$package, StageType::PRE_CONSTRUCTION->value]), [
            'category_selections' => [
                [
                    'category_id' => $selection->template_picture_category_id,
                    'is_selected' => false,
                    'picture_count' => 2,
                    'user_heading' => 'Custom Heading',
                ],
            ],
            'checklist_responses' => [],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($stage->fresh()->status)->toBe(StageStatus::DRAFT_SAVED)
        ->and($stage->fresh()->draft_saved_at)->not->toBeNull();

    $selection->refresh();
    expect($selection->is_selected)->toBeFalse()
        ->and($selection->picture_count)->toBe(3)
        ->and($selection->user_heading)->toBe('Custom Heading');
});

it('submits stages in sequence and submits the package on the final stage', function (): void {
    $fixture = ($this->fixture)();
    $manager = $fixture['manager'];
    $field = $fixture['field'];

    $this->actingAs($manager)->post(route('packages.store', $fixture['project']), [
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);

    $package = CloseoutPackage::firstOrFail();

    ($this->submitStage)($package, StageType::PRE_CONSTRUCTION, $field);
    expect($package->fresh()->current_stage)->toBe(StageType::CONSTRUCTION);

    ($this->submitStage)($package, StageType::CONSTRUCTION, $field);
    expect($package->fresh()->current_stage)->toBe(StageType::POST_COMPLETION);

    ($this->submitStage)($package, StageType::POST_COMPLETION, $field);
    expect($package->fresh()->status)->toBe(PackageStatus::DRAFT);

    $this->actingAs($field)
        ->post(route('packages.submit', $package))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($package->fresh()->status)->toBe(PackageStatus::SUBMITTED)
        ->and($package->fresh()->submitted_at)->not->toBeNull();
});

it('rejects submitting an out-of-order stage', function (): void {
    $fixture = ($this->fixture)();
    $manager = $fixture['manager'];
    $field = $fixture['field'];

    $this->actingAs($manager)->post(route('packages.store', $fixture['project']), [
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);

    $package = CloseoutPackage::firstOrFail();
    $stage = $package->stages()->where('stage', StageType::CONSTRUCTION->value)->firstOrFail();
    $stage->update(['status' => StageStatus::DRAFT_SAVED->value]);

    $this->actingAs($field)
        ->post(route('packages.stages.submit', [$package, StageType::CONSTRUCTION->value]))
        ->assertRedirect()
        ->assertSessionHas('error', 'This stage is not the current stage of the package.');
});

it('rejects submitting a stage without saving a draft', function (): void {
    $fixture = ($this->fixture)();
    $manager = $fixture['manager'];
    $field = $fixture['field'];

    $this->actingAs($manager)->post(route('packages.store', $fixture['project']), [
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);

    $package = CloseoutPackage::firstOrFail();

    $this->actingAs($field)
        ->post(route('packages.stages.submit', [$package, StageType::PRE_CONSTRUCTION->value]))
        ->assertRedirect()
        ->assertSessionHas('error', 'Save a draft before submitting this stage.');
});

it('approves a submitted package as the assigned manager', function (): void {
    $fixture = ($this->fixture)();
    $manager = $fixture['manager'];
    $field = $fixture['field'];

    $this->actingAs($manager)->post(route('packages.store', $fixture['project']), [
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);

    $package = CloseoutPackage::firstOrFail();
    ($this->submitStage)($package, StageType::PRE_CONSTRUCTION, $field);
    ($this->submitStage)($package, StageType::CONSTRUCTION, $field);
    ($this->submitStage)($package, StageType::POST_COMPLETION, $field);

    $this->actingAs($field)
        ->post(route('packages.submit', $package))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->actingAs($fixture['manager'])
        ->post(route('packages.approve', $package))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($package->fresh()->status)->toBe(PackageStatus::APPROVED)
        ->and($package->fresh()->approved_by)->toBe($fixture['manager']->id)
        ->and($package->fresh()->approved_at)->not->toBeNull();
});

it('stamps the project closeout date when a package is approved', function (): void {
    $fixture = ($this->fixture)();
    $package = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);
    $package->update(['status' => PackageStatus::SUBMITTED->value, 'submitted_at' => now()]);

    expect($fixture['project']->fresh()->closeout_package_date)->toBeNull();

    $this->actingAs($fixture['manager'])
        ->post(route('packages.approve', $package))
        ->assertRedirect()
        ->assertSessionHas('success');

    $stamped = $fixture['project']->fresh()->closeout_package_date;
    expect($stamped)->not->toBeNull()
        ->and($stamped->toDateString())->toBe($package->fresh()->approved_at->toDateString());
});

it('rejects a submitted package with a reason', function (): void {
    $fixture = ($this->fixture)();
    $manager = $fixture['manager'];
    $field = $fixture['field'];

    $this->actingAs($manager)->post(route('packages.store', $fixture['project']), [
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);

    $package = CloseoutPackage::firstOrFail();
    $package->update(['status' => PackageStatus::SUBMITTED->value, 'submitted_at' => now()]);

    $this->actingAs($fixture['manager'])
        ->post(route('packages.reject', $package), ['reason' => 'Photos are too dark.'])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($package->fresh()->status)->toBe(PackageStatus::REJECTED);
});

it('requires a reason when rejecting a package', function (): void {
    $fixture = ($this->fixture)();
    $package = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);

    $this->actingAs($fixture['manager'])
        ->post(route('packages.reject', $package), [])
        ->assertSessionHasErrors('reason');
});

it('denies approval to field supervisors', function (): void {
    $fixture = ($this->fixture)();
    $package = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);

    $this->actingAs($fixture['field'])
        ->post(route('packages.approve', $package))
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');
});

it('soft-deletes a package as admin only', function (): void {
    $fixture = ($this->fixture)();
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $package = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('packages.destroy', $package))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertSoftDeleted('closeout_packages', ['id' => $package->id]);
});

it('lands on a working package list after a delete, not a 404', function (): void {
    // The old test only asserted assertRedirect() without checking the target,
    // which is how a redirect that led to a 404 shipped. The client also used
    // to rebuild this URL itself from a bare project id.
    $fixture = ($this->fixture)();
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $project = $fixture['project'];
    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $fixture['template']->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('packages.destroy', $package))
        ->assertRedirect(route('packages.index', $project));

    // The destination itself must resolve.
    $this->actingAs($admin)
        ->get(route('packages.index', $project))
        ->assertOk();
});

it('updates the package site info', function (): void {
    $fixture = ($this->fixture)();
    $package = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);

    $this->actingAs($fixture['field'])
        ->put(route('packages.site-info.update', $package), [
            'site_name' => 'Lomas Peak',
            'vzw_engineer_email' => 'engineer@verizon.example',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($package->siteInfo()->first()->site_name)->toBe('Lomas Peak');
});

it('updates checklist responses in bulk', function (): void {
    $fixture = ($this->fixture)();
    $package = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);

    $checklistItem = $fixture['template']->checklistItems()->firstOrFail();

    $this->actingAs($fixture['field'])
        ->put(route('packages.checklist.update', $package), [
            'responses' => [
                ['checklist_item_id' => $checklistItem->id, 'answer' => false, 'explain' => 'Not yet available'],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $response = $package->checklistResponses()->where('checklist_item_id', $checklistItem->id)->firstOrFail();
    expect($response->answer)->toBeFalse()
        ->and($response->explain)->toBe('Not yet available');
});

it('updates checklist responses with N/A when allowed', function (): void {
    $fixture = ($this->fixture)();
    $package = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);

    $checklistItem = $fixture['template']->checklistItems()->firstOrFail();
    $checklistItem->update(['allows_na' => true]);

    $this->actingAs($fixture['field'])
        ->put(route('packages.checklist.update', $package), [
            'responses' => [
                ['checklist_item_id' => $checklistItem->id, 'is_na' => true],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $response = $package->checklistResponses()->where('checklist_item_id', $checklistItem->id)->firstOrFail();
    expect($response->is_na)->toBeTrue()
        ->and($response->answer)->toBeNull()
        ->and($response->explain)->toBeNull();
});

it('rejects N/A for checklist items that do not allow N/A', function (): void {
    $fixture = ($this->fixture)();
    $package = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);

    $checklistItem = $fixture['template']->checklistItems()->firstOrFail();
    $checklistItem->update(['allows_na' => false]);

    $this->actingAs($fixture['field'])
        ->put(route('packages.checklist.update', $package), [
            'responses' => [
                ['checklist_item_id' => $checklistItem->id, 'is_na' => true],
            ],
        ])
        ->assertSessionHasErrors(['responses.0.is_na']);
});

it('denies draft saving to a field supervisor not assigned to the project', function (): void {
    $fixture = ($this->fixture)();
    $package = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);
    $outsider = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->actingAs($outsider)
        ->post(route('packages.stages.draft', [$package, StageType::PRE_CONSTRUCTION->value]), [
            'category_selections' => [],
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');
});

it('lets a supervisor delete a package they created', function (): void {
    $fixture = ($this->fixture)();
    $package = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
        'created_by' => $fixture['field']->id,
        'status' => 'submitted',
    ]);

    $this->actingAs($fixture['field'])
        ->delete(route('packages.destroy', $package))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertSoftDeleted('closeout_packages', ['id' => $package->id]);
});

it('blocks a supervisor from deleting a package they did not create', function (): void {
    $fixture = ($this->fixture)();
    $other = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $package = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
        'created_by' => $other->id,
    ]);

    $this->actingAs($fixture['field'])
        ->delete(route('packages.destroy', $package))
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');
});

it('lets a manager delete packages on their assigned projects', function (): void {
    $fixture = ($this->fixture)();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $fixture['project']->update(['manager_id' => $manager->id]);
    $package = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
        'status' => 'submitted',
    ]);

    $this->actingAs($manager)
        ->delete(route('packages.destroy', $package))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertSoftDeleted('closeout_packages', ['id' => $package->id]);
});

it('blocks a manager from deleting packages on unassigned projects', function (): void {
    $fixture = ($this->fixture)();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $package = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);

    $this->actingAs($manager)
        ->delete(route('packages.destroy', $package))
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');
});

it('creates a package from a favorite with its snapshot applied', function (): void {
    $fixture = ($this->fixture)();

    $section = $fixture['template']->sections()->where('stage', StageType::PRE_CONSTRUCTION->value)->firstOrFail();
    $category = $section->categories()->firstOrFail();

    $favorite = FavoriteTemplate::factory()->create([
        'user_id' => $fixture['field']->id,
        'template_id' => $fixture['template']->id,
        'name' => 'Ohio Layout',
        'snapshot' => [
            [
                'category_id' => $category->id,
                'is_selected' => false,
                'picture_count' => 4,
                'user_heading' => 'Custom Heading',
                'user_notes' => 'Custom notes',
            ],
        ],
    ]);

    $this->actingAs($fixture['field'])
        ->post(route('packages.store', $fixture['project']), [
            'project_id' => $fixture['project']->id,
            'template_id' => $fixture['template']->id,
            'favorite_id' => $favorite->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $package = CloseoutPackage::firstOrFail();
    $stage = $package->stages()->where('stage', StageType::PRE_CONSTRUCTION->value)->firstOrFail();
    $selection = $stage->categorySelections()
        ->where('template_picture_category_id', $category->id)
        ->firstOrFail();

    expect($selection)
        ->is_selected->toBeFalse()
        ->picture_count->toBe(4)
        ->user_heading->toBe('Custom Heading')
        ->user_notes->toBe('Custom notes');
});

it('clamps crafted snapshot values when creating from a favorite', function (): void {
    $fixture = ($this->fixture)();

    $section = $fixture['template']->sections()->where('stage', StageType::PRE_CONSTRUCTION->value)->firstOrFail();
    $category = $section->categories()->firstOrFail();

    $favorite = FavoriteTemplate::factory()->create([
        'user_id' => $fixture['field']->id,
        'template_id' => $fixture['template']->id,
        'name' => 'Crafted',
        'snapshot' => [
            [
                'category_id' => $category->id,
                'is_selected' => 'false',
                'picture_count' => 99999,
                'user_heading' => ['not', 'a', 'string'],
                'user_notes' => 'ok',
            ],
        ],
    ]);

    $this->actingAs($fixture['field'])
        ->post(route('packages.store', $fixture['project']), [
            'project_id' => $fixture['project']->id,
            'template_id' => $fixture['template']->id,
            'favorite_id' => $favorite->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $package = CloseoutPackage::firstOrFail();
    $stage = $package->stages()->where('stage', StageType::PRE_CONSTRUCTION->value)->firstOrFail();
    $selection = $stage->categorySelections()
        ->where('template_picture_category_id', $category->id)
        ->firstOrFail();

    expect($selection->picture_count)->toBe(99)
        ->and($selection->is_selected)->toBeTrue()
        ->and($selection->user_heading)->toBeNull();
});

it('denies creating a package from another users favorite', function (): void {
    $fixture = ($this->fixture)();
    $other = User::factory()->create();

    $favorite = FavoriteTemplate::factory()->create([
        'user_id' => $other->id,
        'template_id' => $fixture['template']->id,
    ]);

    $this->actingAs($fixture['field'])
        ->post(route('packages.store', $fixture['project']), [
            'project_id' => $fixture['project']->id,
            'template_id' => $fixture['template']->id,
            'favorite_id' => $favorite->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');

    expect(CloseoutPackage::count())->toBe(0);
});

it('denies creating a package from a favorite of a different template', function (): void {
    $fixture = ($this->fixture)();
    $otherTemplate = Template::factory()->create(['market_id' => $fixture['market']->id]);

    $favorite = FavoriteTemplate::factory()->create([
        'user_id' => $fixture['field']->id,
        'template_id' => $otherTemplate->id,
    ]);

    $this->actingAs($fixture['field'])
        ->post(route('packages.store', $fixture['project']), [
            'project_id' => $fixture['project']->id,
            'template_id' => $fixture['template']->id,
            'favorite_id' => $favorite->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');

    expect(CloseoutPackage::count())->toBe(0);
});

it('passes only market-scoped favorites to the packages page', function (): void {
    $this->withoutVite();

    $fixture = ($this->fixture)();
    $otherMarket = Market::factory()->create();
    $otherTemplate = Template::factory()->create(['market_id' => $otherMarket->id]);

    FavoriteTemplate::factory()->create([
        'user_id' => $fixture['field']->id,
        'template_id' => $fixture['template']->id,
        'name' => 'In Market',
    ]);
    FavoriteTemplate::factory()->create([
        'user_id' => $fixture['field']->id,
        'template_id' => $otherTemplate->id,
        'name' => 'Other Market',
    ]);

    $this->actingAs($fixture['field'])
        ->get(route('packages.index', $fixture['project']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Packages/Index')
            ->has('favorites', 1)
            ->where('favorites.0.name', 'In Market'));
});

it('allows updating and re-submitting an earlier stage while package is in draft', function (): void {
    $fixture = ($this->fixture)();
    $manager = $fixture['manager'];
    $field = $fixture['field'];

    $this->actingAs($manager)->post(route('packages.store', $fixture['project']), [
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);

    $package = CloseoutPackage::firstOrFail();
    ($this->submitStage)($package, StageType::PRE_CONSTRUCTION, $field);

    expect($package->fresh()->current_stage)->toBe(StageType::CONSTRUCTION);

    // Tech returns to PRE_CONSTRUCTION and re-submits it with updated data
    ($this->submitStage)($package, StageType::PRE_CONSTRUCTION, $field);

    // current_stage does not regress backwards
    expect($package->fresh()->current_stage)->toBe(StageType::CONSTRUCTION);
});

it('allows the manager to work on their draft package: view Show page, upload auto-approved photos, update checklist, and submit', function (): void {
    $this->withoutVite();
    $fixture = ($this->fixture)();
    $manager = $fixture['manager'];

    $this->actingAs($manager)->post(route('packages.store', $fixture['project']), [
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
    ]);

    $package = CloseoutPackage::firstOrFail();

    // 1. Manager views packages.show and gets the full Packages/Show view (not Review!)
    $this->actingAs($manager)
        ->get(route('packages.show', $package))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Packages/Show')
                ->where('package.id', $package->id)
                ->where('package.status', 'draft')
        );

    // 2. Manager uploads a photo to a category selection
    $stage = $package->stages()->where('stage', StageType::PRE_CONSTRUCTION->value)->firstOrFail();
    $selection = $stage->categorySelections()->firstOrFail();

    $this->actingAs($manager)
        ->post(route('photos.store', [$package, StageType::PRE_CONSTRUCTION->value]), [
            'photo' => UploadedFile::fake()->image('manager_site.jpg'),
            'category_selection_id' => $selection->id,
            'position' => 1,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    // 3. The photo is automatically approved because it was uploaded by the manager
    $photo = Photo::query()->where('package_category_selection_id', $selection->id)->firstOrFail();
    expect($photo->status)->toBe(PhotoStatus::APPROVED)
        ->and($photo->is_approved)->toBeTrue()
        ->and($photo->approved_by)->toBe($manager->id)
        ->and($photo->photoApproval)->not->toBeNull()
        ->and($photo->photoApproval->decision->value)->toBe('approved');

    // 4. Manager can delete the photo in draft
    $this->actingAs($manager)
        ->delete(route('photos.destroy', [$package, $photo]))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Photo::query()->where('id', $photo->id)->exists())->toBeFalse();

    // Re-upload photo for stage submission
    $this->actingAs($manager)
        ->post(route('photos.store', [$package, StageType::PRE_CONSTRUCTION->value]), [
            'photo' => UploadedFile::fake()->image('manager_site2.jpg'),
            'category_selection_id' => $selection->id,
            'position' => 1,
        ]);

    // 5. Manager updates checklist responses
    $checklistItem = $fixture['template']->checklistItems()->firstOrFail();
    $this->actingAs($manager)
        ->put(route('packages.checklist.update', $package), [
            'responses' => [
                [
                    'checklist_item_id' => $checklistItem->id,
                    'answer' => true,
                    'explain' => null,
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    // 6. Manager saves draft and submits stages
    ($this->submitStage)($package, StageType::PRE_CONSTRUCTION, $manager);
    ($this->submitStage)($package, StageType::CONSTRUCTION, $manager);
    ($this->submitStage)($package, StageType::POST_COMPLETION, $manager);

    // Answer all checklist items so package can be submitted
    $allResponses = $fixture['template']->checklistItems()->get()->map(fn ($item) => [
        'checklist_item_id' => $item->id,
        'answer' => true,
    ])->all();
    $this->actingAs($manager)->put(route('packages.checklist.update', $package), [
        'responses' => $allResponses,
    ]);

    // 7. Manager submits the package
    $this->actingAs($manager)
        ->post(route('packages.submit', $package))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($package->fresh()->status)->toBe(PackageStatus::SUBMITTED);

    // 8. Now that it is submitted, viewing packages.show renders Packages/Review for final approval
    $this->actingAs($manager)
        ->get(route('packages.show', $package))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Packages/Review')
        );
});
