<?php

use App\Domains\Markets\Models\Market;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Policies\TemplatePolicy;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
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

it('allows admins every template ability', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $template = Template::factory()->create();
    $policy = app(TemplatePolicy::class);

    expect($policy->viewAny($admin))->toBeTrue()
        ->and($policy->view($admin, $template))->toBeTrue()
        ->and($policy->create($admin))->toBeTrue()
        ->and($policy->update($admin, $template))->toBeTrue()
        ->and($policy->delete($admin, $template))->toBeTrue()
        ->and($policy->duplicate($admin, $template))->toBeTrue();
});

it('allows managers to view templates in their markets only', function (): void {
    $marketA = Market::factory()->create();
    $marketB = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($marketA->id);
    $visible = Template::factory()->create(['market_id' => $marketA->id]);
    $hidden = Template::factory()->create(['market_id' => $marketB->id]);
    $policy = app(TemplatePolicy::class);

    expect($policy->viewAny($manager))->toBeTrue()
        ->and($policy->view($manager, $visible))->toBeTrue()
        ->and($policy->view($manager, $hidden))->toBeFalse()
        ->and($policy->create($manager))->toBeFalse()
        ->and($policy->update($manager, $visible))->toBeFalse()
        ->and($policy->delete($manager, $visible))->toBeFalse()
        ->and($policy->duplicate($manager, $visible))->toBeFalse();
});

it('allows field supervisors to view templates in their markets only', function (): void {
    $marketA = Market::factory()->create();
    $marketB = Market::factory()->create();
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $field->markets()->attach($marketA->id);
    $visible = Template::factory()->create(['market_id' => $marketA->id]);
    $hidden = Template::factory()->create(['market_id' => $marketB->id]);
    $policy = app(TemplatePolicy::class);

    expect($policy->view($field, $visible))->toBeTrue()
        ->and($policy->view($field, $hidden))->toBeFalse()
        ->and($policy->viewAny($field))->toBeFalse()
        ->and($policy->create($field))->toBeFalse();
});
