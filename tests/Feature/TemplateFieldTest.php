<?php

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Actions\LoadFavoriteTemplateAction;
use App\Domains\Templates\Models\FavoriteTemplate;
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
});

it('shows only market-scoped templates to field supervisors', function (): void {
    $this->withoutVite();

    $marketA = Market::factory()->create();
    $marketB = Market::factory()->create();
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $field->markets()->attach($marketA->id);

    $visible = Template::factory()->create(['market_id' => $marketA->id, 'name' => 'Visible Template']);
    Template::factory()->create(['market_id' => $marketB->id, 'name' => 'Hidden Template']);

    $this->actingAs($field)
        ->get(route('templates.selector'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Templates/Index')
            ->has('templates', 1)
            ->where('templates.0.id', $visible->id));
});

it('excludes inactive templates from the field selector', function (): void {
    $this->withoutVite();

    $market = Market::factory()->create();
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $field->markets()->attach($market->id);

    Template::factory()->create(['market_id' => $market->id, 'is_active' => false]);

    $this->actingAs($field)
        ->get(route('templates.selector'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Templates/Index')->has('templates', 0));
});

it('requires authentication for the template selector', function (): void {
    $this->get(route('templates.selector'))->assertRedirect(route('session.expired'));
});

it('saves a named favorite template with a snapshot', function (): void {
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $template = Template::factory()->create();

    $snapshot = [
        ['category_id' => 1, 'picture_count' => 2],
        ['category_id' => 2, 'picture_count' => 1],
    ];

    $this->actingAs($field)
        ->from(route('favorites.index'))
        ->post(route('favorites.store'), [
            'name' => 'My Ohio Favorites',
            'template_id' => $template->id,
            'snapshot' => $snapshot,
        ])
        ->assertRedirect(route('favorites.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('favorite_templates', [
        'user_id' => $field->id,
        'template_id' => $template->id,
        'name' => 'My Ohio Favorites',
    ]);
});

it('lists only the authenticated users favorites', function (): void {
    $this->withoutVite();

    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $other = User::factory()->create();

    $mine = FavoriteTemplate::factory()->create(['user_id' => $field->id]);
    FavoriteTemplate::factory()->create(['user_id' => $other->id]);

    $this->actingAs($field)
        ->get(route('favorites.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Templates/Favorites')
            ->has('favorites', 1)
            ->where('favorites.0.id', $mine->id));
});

it('deletes a favorite template owned by the user', function (): void {
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $favorite = FavoriteTemplate::factory()->create(['user_id' => $field->id]);

    $this->actingAs($field)
        ->from(route('favorites.index'))
        ->delete(route('favorites.destroy', $favorite))
        ->assertRedirect(route('favorites.index'))
        ->assertSessionHas('success');

    expect(FavoriteTemplate::find($favorite->id))->toBeNull();
});

it('denies deleting a favorite owned by another user', function (): void {
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $other = User::factory()->create();
    $favorite = FavoriteTemplate::factory()->create(['user_id' => $other->id]);

    $this->actingAs($field)
        ->delete(route('favorites.destroy', $favorite))
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');
});

it('lists the category count for each favorite on the favorites page', function (): void {
    $this->withoutVite();

    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    FavoriteTemplate::factory()->create([
        'user_id' => $field->id,
        'snapshot' => [
            ['category_id' => 1, 'picture_count' => 2],
            ['category_id' => 2, 'picture_count' => 1],
            ['category_id' => 3, 'picture_count' => 1],
        ],
    ]);

    $this->actingAs($field)
        ->get(route('favorites.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Templates/Favorites')
            ->has('favorites', 1)
            ->where('favorites.0.category_count', 3));
});

it('passes the users favorites to the editable package builder', function (): void {
    $this->withoutVite();

    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $field->markets()->attach($market->id);
    $project = Project::factory()->create([
        'market_id' => $market->id,
        'manager_id' => $manager->id,
    ]);
    $project->fieldSupervisors()->attach($field->id);
    $template = Template::factory()->create(['market_id' => $market->id]);

    $favorite = FavoriteTemplate::factory()->create([
        'user_id' => $field->id,
        'template_id' => $template->id,
        'snapshot' => [
            ['category_id' => 1, 'picture_count' => 2],
        ],
    ]);

    $this->actingAs($field)
        ->post(route('packages.store', $project), [
            'project_id' => $project->id,
            'template_id' => $template->id,
        ])
        ->assertRedirect();

    $package = CloseoutPackage::firstOrFail();

    $this->actingAs($field)
        ->get(route('packages.show', $package))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Packages/Show')
            ->has('favorites', 1)
            ->where('favorites.0.id', $favorite->id));
});

it('loads a favorite snapshot while skipping orphaned categories', function (): void {
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $template = Template::factory()->create();
    $category = TemplatePictureCategory::factory()->create(['name' => 'Grounding']);

    $favorite = FavoriteTemplate::factory()->create([
        'user_id' => $field->id,
        'template_id' => $template->id,
        'snapshot' => [
            ['category_id' => $category->id, 'picture_count' => 1],
            ['category_id' => 99999, 'picture_count' => 3],
        ],
    ]);

    $items = app(LoadFavoriteTemplateAction::class)->execute($favorite);

    expect($items)->toHaveCount(1)
        ->and($items[0]['category_id'])->toBe($category->id);
});

it('hides favorites whose template was deleted from the favorites page', function (): void {
    $this->withoutVite();

    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $liveFavorite = FavoriteTemplate::factory()->create(['user_id' => $field->id]);
    $deletedTemplateFavorite = FavoriteTemplate::factory()->create(['user_id' => $field->id]);

    $deletedTemplateFavorite->template->delete();

    $this->actingAs($field)
        ->get(route('favorites.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Templates/Favorites')
            ->has('favorites', 1)
            ->where('favorites.0.id', $liveFavorite->id));

    // The favorite row itself survives so a template restore brings it back.
    expect(FavoriteTemplate::find($deletedTemplateFavorite->id))->not->toBeNull();
});

it('excludes deleted-template favorites from the package builder options', function (): void {
    $this->withoutVite();

    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $field->markets()->attach($market->id);
    $project = Project::factory()->create([
        'market_id' => $market->id,
        'manager_id' => $manager->id,
    ]);
    $project->fieldSupervisors()->attach($field->id);

    $liveFavorite = FavoriteTemplate::factory()->create([
        'user_id' => $field->id,
        'template_id' => Template::factory()->create(['market_id' => $market->id])->id,
    ]);
    $staleFavorite = FavoriteTemplate::factory()->create([
        'user_id' => $field->id,
        'template_id' => Template::factory()->create(['market_id' => $market->id])->id,
    ]);

    $staleFavorite->template->delete();

    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $liveFavorite->template_id,
        'created_by' => $field->id,
    ]);

    $this->actingAs($field)
        ->get(route('packages.show', $package))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Packages/Show')
            ->has('favorites', 1)
            ->where('favorites.0.id', $liveFavorite->id));
});
