<?php

use App\Domains\Markets\Models\Market;
use App\Domains\Templates\Actions\CreateTemplateAction;
use App\Domains\Templates\Actions\UpdateTemplateAction;
use App\Domains\Templates\DTOs\CreateTemplateDTO;
use App\Domains\Templates\DTOs\UpdateTemplateDTO;
use App\Domains\Templates\Enums\StageType;
use App\Domains\Templates\Models\ChecklistItem;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplatePictureCategory;
use App\Domains\Templates\Services\TemplateCloningService;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use App\Shared\Exceptions\TemplateMarketMissingException;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('creates a template with the three stage sections for an admin', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();

    $template = app(CreateTemplateAction::class)->execute(new CreateTemplateDTO(
        marketId: $market->id,
        name: 'California OSP',
        description: null,
        isActive: true,
    ), $admin);

    expect($template->sections()->count())->toBe(3)
        ->and($template->sections()->pluck('stage')->map(fn ($stage) => $stage->value)->all())
        ->toBe([
            StageType::PRE_CONSTRUCTION->value,
            StageType::CONSTRUCTION->value,
            StageType::POST_COMPLETION->value,
        ]);
});

it('clones a template deeply with sections, categories, and checklist items', function (): void {
    $original = Template::factory()->create(['name' => 'Original']);
    $sectionA = $original->sections()->where('stage', StageType::PRE_CONSTRUCTION)->firstOrFail();
    $sectionB = $original->sections()->where('stage', StageType::CONSTRUCTION)->firstOrFail();
    TemplatePictureCategory::factory()->create(['template_section_id' => $sectionA->id, 'name' => 'MMP', 'sort_order' => 1]);
    TemplatePictureCategory::factory()->create(['template_section_id' => $sectionB->id, 'name' => 'Rack View', 'sort_order' => 2]);
    ChecklistItem::factory()->create(['template_id' => $original->id, 'label' => 'Check A']);
    ChecklistItem::factory()->create(['template_id' => $original->id, 'label' => 'Check B']);

    $copy = app(TemplateCloningService::class)->duplicate($original);

    expect($copy->id)->not->toBe($original->id)
        ->and($copy->market_id)->toBe($original->market_id)
        ->and($copy->sections()->count())->toBe(3)
        ->and($copy->checklistItems()->count())->toBe(2);

    $copyCategories = $copy->sections()->with('categories')->get()->flatMap(fn ($section) => $section->categories)->pluck('name')->sort()->values();
    expect($copyCategories->all())->toBe(['MMP', 'Rack View']);
});

it('clones a template into a different market with a custom name', function (): void {
    $original = Template::factory()->create(['name' => 'Ohio Template']);
    $targetMarket = Market::factory()->create();

    $copy = app(TemplateCloningService::class)->duplicate($original, $targetMarket->id, 'California Copy');

    expect($copy)
        ->market_id->toBe($targetMarket->id)
        ->name->toBe('California Copy');
});

it('refuses to activate a template whose market was deleted', function (): void {
    $template = Template::factory()->create(['is_active' => false]);
    $template->market()->firstOrFail()->delete();

    app(UpdateTemplateAction::class)->execute(
        $template->fresh(),
        new UpdateTemplateDTO(isActive: true),
    );
})->throws(TemplateMarketMissingException::class);

it('re-links an orphaned template to another market and clears the reason', function (): void {
    $template = Template::factory()->create(['is_active' => false, 'inactive_reason' => 'Market deleted.']);
    $template->market()->firstOrFail()->delete();
    $newMarket = Market::factory()->create();

    $updated = app(UpdateTemplateAction::class)->execute(
        $template->fresh(),
        new UpdateTemplateDTO(marketId: $newMarket->id, isActive: true),
    );

    expect($updated->fresh())
        ->market_id->toBe($newMarket->id)
        ->inactive_reason->toBeNull()
        ->is_active->toBeTrue();
});

it('keeps the market of a healthy template fixed on update', function (): void {
    $template = Template::factory()->create();
    $otherMarket = Market::factory()->create();

    $updated = app(UpdateTemplateAction::class)->execute(
        $template,
        new UpdateTemplateDTO(marketId: $otherMarket->id),
    );

    expect($updated->fresh()->market_id)->toBe($template->market_id);
});

it('generates a unique copy name on repeated duplicates', function (): void {
    $original = Template::factory()->create(['name' => 'Ohio Template']);
    $service = app(TemplateCloningService::class);

    $first = $service->duplicate($original);
    $second = $service->duplicate($original);

    expect($first->name)->toBe('Ohio Template Copy')
        ->and($second->name)->toBe('Ohio Template Copy 2');
});
