<?php

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Enums\StageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use App\Domains\CloseoutPackages\Models\PackageChecklistResponse;
use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Actions\CreateTemplateAction;
use App\Domains\Templates\Actions\UpdateTemplateAction;
use App\Domains\Templates\DTOs\CreateTemplateDTO;
use App\Domains\Templates\DTOs\UpdateTemplateDTO;
use App\Domains\Templates\Enums\StageType;
use App\Domains\Templates\Models\ChecklistItem;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplatePictureCategory;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use App\Shared\Exceptions\TemplateNameAlreadyExistsException;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('creates a template and seeds the three stage sections', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.templates.store'), [
            'market_id' => $market->id,
            'name' => 'Ohio 1F Room Ready',
            'description' => 'Ohio market template',
        ])
        ->assertRedirect(route('admin.templates.index'))
        ->assertSessionHas('success');

    $template = Template::where('name', 'Ohio 1F Room Ready')->firstOrFail();

    expect($template)
        ->market_id->toBe($market->id)
        ->is_active->toBeTrue();

    expect($template->sections()->count())->toBe(3)
        ->and($template->sections()->pluck('stage')->map(fn ($stage) => $stage->value)->all())
        ->toBe([StageType::PRE_CONSTRUCTION->value, StageType::CONSTRUCTION->value, StageType::POST_COMPLETION->value]);

    $this->assertDatabaseHas('template_sections', [
        'template_id' => $template->id,
        'stage' => StageType::PRE_CONSTRUCTION->value,
        'heading' => 'Pre Construction',
    ]);
});

it('returns a friendly error when a name race hits the DB unique constraint on create', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();
    Template::factory()->create(['market_id' => $market->id, 'name' => 'Huston Verizon COP']);

    expect(fn (): Template => app(CreateTemplateAction::class)->execute(
        new CreateTemplateDTO(marketId: $market->id, name: 'Huston Verizon COP'),
        $admin,
    ))->toThrow(TemplateNameAlreadyExistsException::class, 'A template with this name already exists in this market.');
});

it('returns a friendly error when a name race hits the DB unique constraint on update', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();
    $template = Template::factory()->create(['market_id' => $market->id, 'name' => 'Ohio 1F']);
    Template::factory()->create(['market_id' => $market->id, 'name' => 'Ohio 2F']);

    expect(fn (): Template => app(UpdateTemplateAction::class)->execute(
        $template,
        new UpdateTemplateDTO(name: 'Ohio 2F'),
    ))->toThrow(TemplateNameAlreadyExistsException::class, 'A template with this name already exists in this market.');
});

it('rejects a duplicate template name within the same market', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();
    Template::factory()->create(['market_id' => $market->id, 'name' => 'Ohio 1F']);

    $this->actingAs($admin)
        ->post(route('admin.templates.store'), [
            'market_id' => $market->id,
            'name' => 'Ohio 1F',
        ])
        ->assertSessionHasErrors('name');
});

it('allows the same template name in different markets', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $marketA = Market::factory()->create();
    $marketB = Market::factory()->create();
    Template::factory()->create(['market_id' => $marketA->id, 'name' => 'Ohio 1F']);

    $this->actingAs($admin)
        ->post(route('admin.templates.store'), [
            'market_id' => $marketB->id,
            'name' => 'Ohio 1F',
        ])
        ->assertSessionHasNoErrors();
});

it('invalidates the templates cache after an update', function (): void {
    Cache::shouldReceive('supportsTags')->andReturn(true);
    $taggedCache = Mockery::mock();
    $taggedCache->shouldReceive('flush')->once();
    Cache::shouldReceive('tags')->with(['templates'])->andReturn($taggedCache);

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $template = Template::factory()->create(['name' => 'Old Name']);

    $this->actingAs($admin)
        ->put(route('admin.templates.update', $template), [
            'name' => 'New Name',
            'description' => 'Updated description',
        ])
        ->assertRedirect(route('admin.templates.index'));
});

it('cannot activate an orphaned template until it is linked to a market', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $template = Template::factory()->create(['is_active' => false]);
    $template->market()->firstOrFail()->delete();

    $this->actingAs($admin)
        ->put(route('admin.templates.update', $template), [
            'name' => $template->name,
            'is_active' => true,
        ])
        ->assertSessionHas('error', 'Link this template to a market before activating it.');

    $newMarket = Market::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.templates.update', $template->fresh()), [
            'name' => $template->name,
            'market_id' => $newMarket->id,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.templates.index'))
        ->assertSessionHas('success');

    expect($template->fresh())
        ->market_id->toBe($newMarket->id)
        ->inactive_reason->toBeNull()
        ->is_active->toBeTrue();
});

it('updates a template', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $template = Template::factory()->create(['name' => 'Old Name']);

    $this->actingAs($admin)
        ->put(route('admin.templates.update', $template), [
            'name' => 'New Name',
            'description' => 'Updated description',
            'is_active' => false,
        ])
        ->assertRedirect(route('admin.templates.index'))
        ->assertSessionHas('success');

    expect($template->fresh())
        ->name->toBe('New Name')
        ->description->toBe('Updated description')
        ->is_active->toBeFalse();
});

it('rejects deleting a template used by close-out packages', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $template = Template::factory()->create();
    CloseoutPackage::factory()->create([
        'project_id' => Project::factory()->create(['market_id' => $template->market_id])->id,
        'template_id' => $template->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.templates.destroy', $template))
        ->assertRedirect()
        ->assertSessionHas('error', 'This template is used by close-out packages and cannot be deleted.');

    expect(Template::find($template->id))->not->toBeNull();
});

it('allows deleting a template whose only close-out packages are soft-deleted', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $template = Template::factory()->create();
    CloseoutPackage::factory()->create([
        'project_id' => Project::factory()->create(['market_id' => $template->market_id])->id,
        'template_id' => $template->id,
    ])->delete();

    $this->actingAs($admin)
        ->delete(route('admin.templates.destroy', $template))
        ->assertRedirect(route('admin.templates.index'))
        ->assertSessionHas('success');

    expect($template->fresh()->trashed())->toBeTrue();
});

it('allows deleting a template whose only active packages belong to soft-deleted projects', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $template = Template::factory()->create();
    $project = Project::factory()->create(['market_id' => $template->market_id]);
    CloseoutPackage::factory()->create(['project_id' => $project->id, 'template_id' => $template->id]);
    $project->delete();

    $this->actingAs($admin)
        ->delete(route('admin.templates.destroy', $template))
        ->assertRedirect(route('admin.templates.index'))
        ->assertSessionHas('success');

    expect($template->fresh()->trashed())->toBeTrue();
});

it('allows recreating a template with the same name after deletion', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();
    $template = Template::factory()->create(['market_id' => $market->id, 'name' => 'Ohio Market']);

    $this->actingAs($admin)
        ->delete(route('admin.templates.destroy', $template))
        ->assertRedirect(route('admin.templates.index'));

    $this->actingAs($admin)
        ->post(route('admin.templates.store'), [
            'market_id' => $market->id,
            'name' => 'Ohio Market',
        ])
        ->assertRedirect(route('admin.templates.index'))
        ->assertSessionHas('success');

    expect(Template::withTrashed()->where('market_id', $market->id)->where('name', 'Ohio Market')->count())->toBe(2)
        ->and(Template::where('market_id', $market->id)->where('name', 'Ohio Market')->count())->toBe(1);
});

it('soft-deletes a template as admin only', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $template = Template::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.templates.destroy', $template))
        ->assertRedirect(route('admin.templates.index'));

    $this->assertSoftDeleted('templates', ['id' => $template->id]);
});

it('denies template management to managers', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);

    $this->actingAs($manager)
        ->get(route('admin.templates.index'))
        ->assertForbidden();
});

it('adds a picture category to a section', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $template = Template::factory()->create();
    $section = $template->sections()->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.templates.categories.store', [$template, $section]), [
            'name' => 'MMP',
            'description' => 'Main meter panel',
            'default_picture_count' => 2,
            'sort_order' => 1,
        ])
        ->assertRedirect(route('admin.templates.show', $template))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('template_picture_categories', [
        'template_section_id' => $section->id,
        'name' => 'MMP',
        'default_picture_count' => 2,
        'sort_order' => 1,
    ]);
});

it('rejects a category from another section via reorder', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $template = Template::factory()->create();
    $sectionA = $template->sections()->where('stage', StageType::PRE_CONSTRUCTION->value)->firstOrFail();
    $sectionB = $template->sections()->where('stage', StageType::CONSTRUCTION->value)->firstOrFail();
    $foreignCategory = TemplatePictureCategory::factory()->create(['template_section_id' => $sectionB->id]);

    $this->actingAs($admin)
        ->post(route('admin.templates.categories.reorder', [$template, $sectionA]), [
            'items' => [
                ['id' => $foreignCategory->id, 'sort_order' => 5],
            ],
        ])
        ->assertSessionHasErrors('items.0.id');
});

it('reorders categories within a section', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $template = Template::factory()->create();
    $section = $template->sections()->firstOrFail();
    $categoryA = TemplatePictureCategory::factory()->create(['template_section_id' => $section->id, 'sort_order' => 1]);
    $categoryB = TemplatePictureCategory::factory()->create(['template_section_id' => $section->id, 'sort_order' => 2]);

    $this->actingAs($admin)
        ->post(route('admin.templates.categories.reorder', [$template, $section]), [
            'items' => [
                ['id' => $categoryA->id, 'sort_order' => 2],
                ['id' => $categoryB->id, 'sort_order' => 1],
            ],
        ])
        ->assertRedirect(route('admin.templates.show', $template));

    expect($categoryA->fresh()->sort_order)->toBe(2)
        ->and($categoryB->fresh()->sort_order)->toBe(1);
});

it('deletes a picture category', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $template = Template::factory()->create();
    $section = $template->sections()->firstOrFail();
    $category = TemplatePictureCategory::factory()->create(['template_section_id' => $section->id]);

    $this->actingAs($admin)
        ->delete(route('admin.templates.categories.destroy', [$template, $section, $category]))
        ->assertRedirect(route('admin.templates.show', $template));

    expect(TemplatePictureCategory::find($category->id))->toBeNull();
});

it('deletes a picture category even when used in existing closeout packages without integrity constraint error', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $template = Template::factory()->create();
    $section = $template->sections()->firstOrFail();
    $category = TemplatePictureCategory::factory()->create(['template_section_id' => $section->id]);

    $project = Project::factory()->create(['market_id' => $template->market_id]);
    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
    ]);
    $stage = $package->stages()->create([
        'stage' => $section->stage,
        'status' => StageStatus::DRAFT_SAVED,
    ]);
    PackageCategorySelection::create([
        'package_stage_id' => $stage->id,
        'template_picture_category_id' => $category->id,
        'is_selected' => true,
        'picture_count' => 1,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.templates.categories.destroy', [$template, $section, $category]))
        ->assertRedirect(route('admin.templates.show', $template));

    expect(TemplatePictureCategory::find($category->id))->toBeNull()
        ->and(TemplatePictureCategory::withTrashed()->find($category->id))->not->toBeNull();
});
it('deletes photo-less category selections in under-review packages when template category is deleted', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $template = Template::factory()->create();
    $section = $template->sections()->firstOrFail();
    $category = TemplatePictureCategory::factory()->create(['template_section_id' => $section->id]);

    $project = Project::factory()->create(['market_id' => $template->market_id]);
    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
        'status' => PackageStatus::UNDER_REVIEW,
    ]);
    $stage = $package->stages()->create([
        'stage' => $section->stage,
        'status' => StageStatus::SUBMITTED,
    ]);
    $selection = PackageCategorySelection::create([
        'package_stage_id' => $stage->id,
        'template_picture_category_id' => $category->id,
        'is_selected' => true,
        'picture_count' => 1,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.templates.categories.destroy', [$template, $section, $category]))
        ->assertRedirect(route('admin.templates.show', $template));

    expect(PackageCategorySelection::find($selection->id))->toBeNull();
});

it('deletes a checklist item even when referenced by package checklist responses', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $template = Template::factory()->create();
    $item = ChecklistItem::factory()->create(['template_id' => $template->id]);

    $project = Project::factory()->create(['market_id' => $template->market_id]);
    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
    ]);
    PackageChecklistResponse::create([
        'closeout_package_id' => $package->id,
        'checklist_item_id' => $item->id,
        'answer' => null,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.templates.checklist.destroy', [$template, $item]))
        ->assertRedirect(route('admin.templates.show', $template));

    expect(ChecklistItem::find($item->id))->toBeNull()
        ->and(ChecklistItem::withTrashed()->find($item->id))->not->toBeNull();
});

it('updates a section heading as admin', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $template = Template::factory()->create();
    $section = $template->sections()->firstOrFail();

    $this->actingAs($admin)
        ->put(route('admin.templates.sections.update', [$template, $section]), [
            'heading' => 'PRE CON PICTURES',
            'admin_notes' => 'Take clear wide shots.',
        ])
        ->assertRedirect(route('admin.templates.show', $template));

    expect($section->fresh())
        ->heading->toBe('PRE CON PICTURES')
        ->admin_notes->toBe('Take clear wide shots.');
});

it('manages checklist items', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $template = Template::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.templates.checklist.store', $template), [
            'label' => 'Site access granted?',
            'has_explain_field' => true,
            'sort_order' => 1,
        ])
        ->assertRedirect(route('admin.templates.show', $template));

    $item = ChecklistItem::firstOrFail();
    expect($item)
        ->template_id->toBe($template->id)
        ->label->toBe('Site access granted?')
        ->has_explain_field->toBeTrue();

    $this->actingAs($admin)
        ->put(route('admin.templates.checklist.update', [$template, $item]), [
            'label' => 'Updated label',
        ])
        ->assertRedirect(route('admin.templates.show', $template));

    expect($item->fresh()->label)->toBe('Updated label');

    $this->actingAs($admin)
        ->delete(route('admin.templates.checklist.destroy', [$template, $item]))
        ->assertRedirect(route('admin.templates.show', $template));

    expect(ChecklistItem::find($item->id))->toBeNull();
});

it('duplicates a template with all sections, categories, and checklist items', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $template = Template::factory()->create(['name' => 'Original']);
    $section = $template->sections()->firstOrFail();
    $category = TemplatePictureCategory::factory()->create(['template_section_id' => $section->id, 'name' => 'Grounding']);
    $checklist = ChecklistItem::factory()->create(['template_id' => $template->id, 'label' => 'Label A']);

    $this->actingAs($admin)
        ->post(route('admin.templates.duplicate', $template))
        ->assertSessionHas('success');

    $copy = Template::where('name', 'like', 'Original Copy%')->firstOrFail();

    expect($copy->id)->not->toBe($template->id)
        ->and($copy->sections()->count())->toBe(3)
        ->and($copy->checklistItems()->count())->toBe(1)
        ->and($copy->sections()->first()->categories()->count())->toBe(1)
        ->and($copy->sections()->first()->categories()->first()->name)->toBe('Grounding')
        ->and($copy->checklistItems()->first()->label)->toBe('Label A');
});

it('rejects a template subcategory with name exceeding 100 characters or description exceeding 500 characters', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $template = Template::factory()->create();
    $section = $template->sections()->firstOrFail();

    $this->actingAs($admin)
        ->post(route('admin.templates.categories.store', [$template, $section]), [
            'name' => str_repeat('x', 101),
            'description' => str_repeat('y', 501),
            'default_picture_count' => 1,
            'sort_order' => 0,
        ])
        ->assertSessionHasErrors(['name', 'description']);

    expect($section->categories()->count())->toBe(0);
});
