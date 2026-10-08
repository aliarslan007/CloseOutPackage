<?php

use App\Domains\Markets\Models\Market;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);

    $this->actingAs($manager);
});

it('proxies hub filter options from the Auth Center', function (): void {
    Http::fake([
        'server.test/api/pm/sites/filter-options*' => Http::response([
            'success' => true,
            'regions' => ['Midwest', 'South'],
            'states' => [],
        ]),
    ]);

    $this->getJson(route('manager.packages.auth-sites.filter-options', ['type' => 'hub']))
        ->assertOk()
        ->assertJson([
            'success' => true,
            'regions' => ['Midwest', 'South'],
            'states' => [],
        ]);

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/api/pm/sites/filter-options')
            && $request->data()['type'] === 'hub'
            && $request->hasHeader('X-PM-Secret');
    });
});

it('proxies state filter options when a region is selected', function (): void {
    Http::fake([
        'server.test/api/pm/sites/filter-options*' => Http::response([
            'success' => true,
            'regions' => ['Midwest'],
            'states' => [['code' => 'OH', 'name' => 'Ohio']],
        ]),
    ]);

    $this->getJson(route('manager.packages.auth-sites.filter-options', ['type' => 'hub', 'region' => 'Midwest']))
        ->assertOk()
        ->assertJson([
            'success' => true,
            'regions' => ['Midwest'],
            'states' => [['code' => 'OH', 'name' => 'Ohio']],
        ]);
});

it('normalizes hub sites from the Auth Center', function (): void {
    Http::fake([
        'server.test/api/pm/sites*' => Http::response([
            'success' => true,
            'type' => 'hub',
            'data' => [
                ['id' => 433, 'ohpa_site' => 'AKRON ALLTEL MTSO -- 1875031'],
                ['id' => 435, 'ohpa_site' => 'Akron MTSO'],
            ],
        ]),
    ]);

    $this->getJson(route('manager.packages.auth-sites', ['type' => 'hub', 'region' => 'Midwest', 'state' => 'OH']))
        ->assertOk()
        ->assertJson([
            'success' => true,
            'sites' => [
                ['id' => 433, 'name' => 'AKRON ALLTEL MTSO -- 1875031'],
                ['id' => 435, 'name' => 'Akron MTSO'],
            ],
        ]);

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/api/pm/sites')
            && $request->data()['region'] === 'Midwest'
            && $request->data()['state'] === 'OH';
    });
});

it('normalizes cell sites from the Auth Center using site_name', function (): void {
    Http::fake([
        'server.test/api/pm/sites*' => Http::response([
            'success' => true,
            'type' => 'smallcell',
            'data' => [
                ['id' => 12, 'site_name' => 'Columbus Main'],
            ],
        ]),
    ]);

    $this->getJson(route('manager.packages.auth-sites', ['type' => 'smallcell']))
        ->assertOk()
        ->assertJson([
            'success' => true,
            'sites' => [['id' => 12, 'name' => 'Columbus Main']],
        ]);
});

it('rejects an unknown site type', function (): void {
    $this->getJson(route('manager.packages.auth-sites', ['type' => 'tower']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('type');
});

it('surfaces Auth Center failures as 422', function (): void {
    Http::fake([
        'server.test/api/pm/sites/filter-options*' => Http::response([
            'success' => false,
            'message' => 'Unauthorized',
        ], 401),
    ]);

    $this->getJson(route('manager.packages.auth-sites.filter-options', ['type' => 'hub']))
        ->assertStatus(422)
        ->assertJson(['success' => false, 'message' => 'Unauthorized']);
});

it('requires a manager role', function (): void {
    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);

    $this->actingAs($customer)
        ->getJson(route('manager.packages.auth-sites', ['type' => 'hub']))
        ->assertForbidden();
});
