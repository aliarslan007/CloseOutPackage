<?php

use App\Domains\Markets\Models\Market;
use App\Domains\Templates\Enums\StageType;
use App\Domains\Templates\Models\CategoryPreset;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplatePictureCategory;
use App\Domains\Templates\Models\TemplateSection;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $this->template = Template::factory()->create(['market_id' => Market::factory()->create()->id]);
    // Template::factory() auto-creates the three stage sections; grab stage 1.
    $this->section = $this->template->sections()->where('stage', StageType::PRE_CONSTRUCTION)->firstOrFail();
});

function pullRoute(Template $template, TemplateSection $section): string
{
    return route('admin.templates.categories.from-library', [$template, $section]);
}

it('pulls selected presets into a stage as copied categories', function (): void {
    $preset = CategoryPreset::factory()->create([
        'name' => 'Tower Foundation',
        'default_picture_count' => 3,
        'is_required' => true,
    ]);

    $this->actingAs($this->admin)
        ->post(pullRoute($this->template, $this->section), ['preset_ids' => [$preset->id]])
        ->assertRedirect(route('admin.templates.show', $this->template))
        ->assertSessionHas('success', 'Added 1 photo group(s).');

    $this->assertDatabaseHas('template_picture_categories', [
        'template_section_id' => $this->section->id,
        'category_preset_id' => $preset->id,
        'name' => 'Tower Foundation',
        'default_picture_count' => 3,
        'is_required' => 1,
    ]);
});

it('rejects inactive presets when pulling', function (): void {
    $preset = CategoryPreset::factory()->inactive()->create();

    $this->actingAs($this->admin)
        ->post(pullRoute($this->template, $this->section), ['preset_ids' => [$preset->id]])
        ->assertSessionHasErrors('preset_ids.0');

    expect(TemplatePictureCategory::count())->toBe(0);
});

it('skips presets already pulled into the same stage and reports it', function (): void {
    $preset = CategoryPreset::factory()->create();
    TemplatePictureCategory::factory()->create([
        'template_section_id' => $this->section->id,
        'category_preset_id' => $preset->id,
        'sort_order' => 0,
    ]);

    $this->actingAs($this->admin)
        ->post(pullRoute($this->template, $this->section), ['preset_ids' => [$preset->id]])
        ->assertSessionHas('error', 'Those photo groups are already in this stage.');

    expect(TemplatePictureCategory::count())->toBe(1);
});

it('reports a mixed pull with added and skipped counts', function (): void {
    $pulled = CategoryPreset::factory()->create();
    $fresh = CategoryPreset::factory()->create();
    TemplatePictureCategory::factory()->create([
        'template_section_id' => $this->section->id,
        'category_preset_id' => $pulled->id,
        'sort_order' => 4,
    ]);

    $this->actingAs($this->admin)
        ->post(pullRoute($this->template, $this->section), [
            'preset_ids' => [$pulled->id, $fresh->id],
        ])
        ->assertSessionHas('success', 'Added 1 photo group(s), 1 already in this stage.');

    expect($fresh->id)->not->toBeNull()
        ->and(TemplatePictureCategory::where('category_preset_id', $fresh->id)->first()->sort_order)
        ->toBe(5);
});

it('allows the same preset in different stages', function (): void {
    $preset = CategoryPreset::factory()->create();
    $otherStage = $this->template->sections()->where('stage', StageType::CONSTRUCTION)->firstOrFail();
    TemplatePictureCategory::factory()->create([
        'template_section_id' => $this->section->id,
        'category_preset_id' => $preset->id,
    ]);

    $this->actingAs($this->admin)
        ->post(pullRoute($this->template, $otherStage), ['preset_ids' => [$preset->id]])
        ->assertSessionHas('success');

    expect(TemplatePictureCategory::where('category_preset_id', $preset->id)->count())->toBe(2);
});

it('blocks a manually created duplicate name within the same stage case-insensitively', function (): void {
    TemplatePictureCategory::factory()->create([
        'template_section_id' => $this->section->id,
        'name' => 'Tower Foundation',
        'sort_order' => 0,
    ]);

    $this->actingAs($this->admin)
        ->post(
            route('admin.templates.categories.store', [$this->template, $this->section]),
            ['name' => '  tower foundation ', 'default_picture_count' => 1, 'sort_order' => 1],
        )
        ->assertSessionHasErrors('name');

    expect(TemplatePictureCategory::count())->toBe(1);
});

it('allows the same manual name in a different stage', function (): void {
    TemplatePictureCategory::factory()->create([
        'template_section_id' => $this->section->id,
        'name' => 'Tower Foundation',
        'sort_order' => 0,
    ]);
    $otherStage = $this->template->sections()->where('stage', StageType::POST_COMPLETION)->firstOrFail();

    $this->actingAs($this->admin)
        ->post(
            route('admin.templates.categories.store', [$this->template, $otherStage]),
            ['name' => 'Tower Foundation', 'default_picture_count' => 1, 'sort_order' => 0],
        )
        ->assertSessionHasNoErrors();

    expect(TemplatePictureCategory::where('name', 'Tower Foundation')->count())->toBe(2);
});

it('allows renaming a category to its own current name', function (): void {
    $category = TemplatePictureCategory::factory()->create([
        'template_section_id' => $this->section->id,
        'name' => 'Antenna Mount',
        'sort_order' => 0,
    ]);

    $this->actingAs($this->admin)
        ->put(
            route('admin.templates.categories.update', [$this->template, $this->section, $category]),
            ['name' => 'antenna mount', 'default_picture_count' => 2, 'sort_order' => 0],
        )
        ->assertSessionHasNoErrors();
});

it('offers only active presets on the builder page', function (): void {
    $this->withoutVite();

    CategoryPreset::factory()->create(['name' => 'Active One']);
    CategoryPreset::factory()->inactive()->create(['name' => 'Hidden One']);

    $this->actingAs($this->admin)
        ->get(route('admin.templates.show', $this->template))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Templates/Show')
            ->has('libraryPresets', 1)
            ->where('libraryPresets.0.name', 'Active One'));
});

it('allows re-pulling a preset after its category was deleted from the stage', function (): void {
    $preset = CategoryPreset::factory()->create(['name' => 'Tower Foundation']);

    $this->actingAs($this->admin)
        ->post(pullRoute($this->template, $this->section), ['preset_ids' => [$preset->id]])
        ->assertSessionHas('success', 'Added 1 photo group(s).');

    $category = TemplatePictureCategory::where('category_preset_id', $preset->id)->firstOrFail();

    $this->actingAs($this->admin)
        ->delete(route('admin.templates.categories.destroy', [$this->template, $this->section, $category]))
        ->assertRedirect(route('admin.templates.show', $this->template));

    expect(TemplatePictureCategory::find($category->id))->toBeNull();

    // Re-pulling the same preset into the same stage must succeed
    $this->actingAs($this->admin)
        ->post(pullRoute($this->template, $this->section), ['preset_ids' => [$preset->id]])
        ->assertRedirect(route('admin.templates.show', $this->template))
        ->assertSessionHas('success', 'Added 1 photo group(s).');

    expect(TemplatePictureCategory::where('name', 'Tower Foundation')->count())->toBe(1);
});
