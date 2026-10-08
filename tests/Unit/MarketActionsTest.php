<?php

use App\Domains\Markets\Actions\CreateMarketAction;
use App\Domains\Markets\Actions\DeleteMarketAction;
use App\Domains\Markets\Actions\UpdateMarketAction;
use App\Domains\Markets\DTOs\CreateMarketDTO;
use App\Domains\Markets\DTOs\UpdateMarketDTO;
use App\Domains\Markets\Events\MarketCreated;
use App\Domains\Markets\Models\Market;
use App\Domains\Templates\Models\Template;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('creates a market with a slugified name and fires MarketCreated', function (): void {
    Event::fake([MarketCreated::class]);

    $market = app(CreateMarketAction::class)->execute(new CreateMarketDTO(
        name: 'California OSP',
        description: 'A market',
        isActive: true,
    ));

    expect($market)
        ->name->toBe('California OSP')
        ->slug->toBe('california-osp')
        ->is_active->toBeTrue();

    Event::assertDispatched(MarketCreated::class, fn (MarketCreated $event): bool => $event->market->is($market));
});

it('disambiguates slug collisions with a numeric suffix', function (): void {
    $action = app(CreateMarketAction::class);
    $action->execute(new CreateMarketDTO(name: 'Ohio'));

    $market = $action->execute(new CreateMarketDTO(name: 'Ohio!'));

    expect($market->slug)->toBe('ohio-2');
});

it('deactivates the market templates with a reason instead of deleting them', function (): void {
    $market = Market::factory()->create(['name' => 'Los Angeles']);
    $template = Template::factory()->create(['market_id' => $market->id, 'is_active' => true]);
    $otherTemplate = Template::factory()->create(['is_active' => true]);

    app(DeleteMarketAction::class)->execute($market);

    expect($template->fresh())
        ->is_active->toBeFalse()
        ->inactive_reason->toContain('Los Angeles')
        ->and($otherTemplate->fresh()->is_active)->toBeTrue();
});

it('updates only the provided fields and regenerates the slug on rename', function (): void {
    $market = Market::factory()->create(['name' => 'Ohio', 'slug' => 'ohio']);

    $updated = app(UpdateMarketAction::class)->execute($market, new UpdateMarketDTO(
        name: 'Ohio East',
        description: null,
        isActive: null,
    ));

    expect($updated->fresh())
        ->name->toBe('Ohio East')
        ->slug->toBe('ohio-east')
        ->description->toBe($market->description)
        ->is_active->toBeTrue();
});
