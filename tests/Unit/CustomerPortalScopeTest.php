<?php

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\CustomerPortal\Policies\CustomerPortalPolicy;
use App\Domains\CustomerPortal\Services\CustomerPortalScopeService;
use App\Domains\Markets\Models\Market;
use App\Domains\Photos\Enums\PhotoStatus;
use App\Domains\Photos\Models\Photo;
use App\Domains\Photos\Policies\PhotoPolicy;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Models\Template;
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

    $this->customer = fn (): User => User::factory()->create()->assignRole(Role::CUSTOMER->value);
});

it('scopes accessible projects to active grants', function (): void {
    $customer = ($this->customer)();
    $market = Market::factory()->create();
    $granted = Project::factory()->create(['market_id' => $market->id]);
    $expired = Project::factory()->create(['market_id' => $market->id]);
    $other = Project::factory()->create();

    CustomerProjectAccess::factory()->create(['user_id' => $customer->id, 'project_id' => $granted->id]);
    CustomerProjectAccess::factory()->create([
        'user_id' => $customer->id,
        'project_id' => $expired->id,
        'expires_at' => now()->subDay(),
    ]);

    $projects = app(CustomerPortalScopeService::class)->accessibleProjects($customer)->pluck('id');

    expect($projects)->toContain($granted->id)
        ->not->toContain($expired->id)
        ->not->toContain($other->id);
});

it('grants policy access for active grants on packages in accessible projects', function (): void {
    $customer = ($this->customer)();
    $market = Market::factory()->create();
    $project = Project::factory()->create(['market_id' => $market->id]);
    $template = Template::factory()->create(['market_id' => $market->id]);
    $approved = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
        'status' => PackageStatus::APPROVED->value,
    ]);
    $draft = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
        'status' => PackageStatus::DRAFT->value,
    ]);

    $policy = app(CustomerPortalPolicy::class);

    expect($policy->viewProject($customer, $project))->toBeFalse()
        ->and($policy->viewPackage($customer, $approved))->toBeFalse()
        ->and($policy->viewPackage($customer, $draft))->toBeFalse()
        ->and($policy->downloadPdf($customer, $approved))->toBeFalse();

    CustomerProjectAccess::factory()->create(['user_id' => $customer->id, 'project_id' => $project->id]);

    expect($policy->viewProject($customer, $project))->toBeTrue()
        ->and($policy->viewPackage($customer, $approved))->toBeTrue()
        ->and($policy->viewPackage($customer, $draft))->toBeFalse()
        ->and($policy->downloadPdf($customer, $approved))->toBeTrue();
});

it('lets customers view approved photos of approved packages in projects they can access and denies drafts/pending', function (): void {
    $customer = ($this->customer)();
    $market = Market::factory()->create();
    $project = Project::factory()->create(['market_id' => $market->id]);
    $template = Template::factory()->create(['market_id' => $market->id]);
    $approvedPackage = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
        'status' => PackageStatus::APPROVED->value,
    ]);
    $approved = Photo::factory()->create([
        'closeout_package_id' => $approvedPackage->id,
        'is_approved' => true,
        'status' => PhotoStatus::APPROVED->value,
    ]);
    $pending = Photo::factory()->create([
        'closeout_package_id' => $approvedPackage->id,
        'is_approved' => null,
        'status' => PhotoStatus::PENDING->value,
    ]);
    $draftPackage = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
        'status' => PackageStatus::DRAFT->value,
    ]);
    $photoInDraft = Photo::factory()->create([
        'closeout_package_id' => $draftPackage->id,
        'is_approved' => null,
        'status' => PhotoStatus::PENDING->value,
    ]);
    $replaced = Photo::factory()->create([
        'closeout_package_id' => $approvedPackage->id,
        'replaced_at' => now(),
    ]);

    $policy = app(PhotoPolicy::class);

    expect($policy->viewPhoto($customer, $approved))->toBeFalse()
        ->and($policy->viewPhoto($customer, $pending))->toBeFalse()
        ->and($policy->viewPhoto($customer, $photoInDraft))->toBeFalse();

    CustomerProjectAccess::factory()->create(['user_id' => $customer->id, 'project_id' => $project->id]);

    expect($policy->viewPhoto($customer, $approved))->toBeTrue()
        ->and($policy->viewPhoto($customer, $pending))->toBeFalse()
        ->and($policy->viewPhoto($customer, $photoInDraft))->toBeFalse()
        ->and($policy->viewPhoto($customer, $replaced))->toBeFalse();
});

it('expires customer access records older than their expiry', function (): void {
    $customer = ($this->customer)();
    $project = Project::factory()->create();
    CustomerProjectAccess::factory()->create([
        'user_id' => $customer->id,
        'project_id' => $project->id,
        'expires_at' => now()->subHour(),
    ]);
    CustomerProjectAccess::factory()->create([
        'user_id' => $customer->id,
        'project_id' => Project::factory()->create()->id,
        'expires_at' => now()->addDay(),
    ]);

    $this->artisan('cop:expire-customer-access')->assertSuccessful();

    expect(CustomerProjectAccess::count())->toBe(1);
});
