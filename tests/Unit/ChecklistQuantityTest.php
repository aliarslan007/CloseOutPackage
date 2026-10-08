<?php

declare(strict_types=1);

use App\Domains\CloseoutPackages\Actions\SubmitPackageAction;
use App\Domains\CloseoutPackages\Actions\UpdatePackageChecklistAction;
use App\Domains\CloseoutPackages\DTOs\UpdatePackageChecklistDTO;
use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Enums\StageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageChecklistResponse;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Models\ChecklistItem;
use App\Domains\Templates\Models\Template;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use App\Shared\Exceptions\ChecklistIncompleteException;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->draftPackage = function (): array {
        $market = Market::factory()->create();
        $project = Project::factory()->create(['market_id' => $market->id]);
        $template = Template::factory()->create(['market_id' => $market->id]);
        $package = CloseoutPackage::factory()->create([
            'project_id' => $project->id,
            'template_id' => $template->id,
            'status' => PackageStatus::DRAFT,
        ]);
        $item = ChecklistItem::factory()->create(['template_id' => $template->id]);

        return ['package' => $package, 'item' => $item];
    };

    $this->responseFor = fn (CloseoutPackage $package, ChecklistItem $item): PackageChecklistResponse => PackageChecklistResponse::query()
        ->where('closeout_package_id', $package->id)
        ->where('checklist_item_id', $item->id)
        ->firstOrFail();
});

it('stores a quantity alongside a yes answer', function (): void {
    ['package' => $package, 'item' => $item] = ($this->draftPackage)();

    app(UpdatePackageChecklistAction::class)->execute($package, new UpdatePackageChecklistDTO([
        [
            'checklist_item_id' => $item->id,
            'answer' => true,
            'explain' => null,
            'is_na' => false,
            'quantity' => 2,
        ],
    ]));

    $response = ($this->responseFor)($package, $item);

    expect($response->quantity)->toBe(2)
        ->and($response->answer)->toBeTrue();
});

it('leaves the quantity empty when none was provided', function (): void {
    ['package' => $package, 'item' => $item] = ($this->draftPackage)();

    app(UpdatePackageChecklistAction::class)->execute($package, new UpdatePackageChecklistDTO([
        [
            'checklist_item_id' => $item->id,
            'answer' => true,
            'explain' => null,
            'is_na' => false,
        ],
    ]));

    expect(($this->responseFor)($package, $item)->quantity)->toBeNull();
});

it('clears a stored quantity when the item is answered again without one', function (): void {
    ['package' => $package, 'item' => $item] = ($this->draftPackage)();

    app(UpdatePackageChecklistAction::class)->execute($package, new UpdatePackageChecklistDTO([
        ['checklist_item_id' => $item->id, 'answer' => false, 'quantity' => 5],
    ]));

    expect(($this->responseFor)($package, $item)->quantity)->toBe(5);

    app(UpdatePackageChecklistAction::class)->execute($package, new UpdatePackageChecklistDTO([
        ['checklist_item_id' => $item->id, 'answer' => false],
    ]));

    expect(($this->responseFor)($package, $item)->quantity)->toBeNull();
});

it('drops the quantity when the item is marked N/A', function (): void {
    ['package' => $package, 'item' => $item] = ($this->draftPackage)();
    $item->update(['allows_na' => true]);

    $this->actingAs(User::factory()->create()->assignRole(Role::ADMIN->value))
        ->put(route('packages.checklist.update', $package), [
            'responses' => [
                ['checklist_item_id' => $item->id, 'is_na' => true, 'quantity' => 5],
            ],
        ])
        ->assertSessionHasNoErrors();

    $response = ($this->responseFor)($package, $item);

    expect($response->is_na)->toBeTrue()
        ->and($response->quantity)->toBeNull()
        ->and($response->answer)->toBeNull();
});

it('rejects an out-of-range or non-integer quantity', function (int|float $quantity): void {
    ['package' => $package, 'item' => $item] = ($this->draftPackage)();

    $this->actingAs(User::factory()->create()->assignRole(Role::ADMIN->value))
        ->put(route('packages.checklist.update', $package), [
            'responses' => [
                ['checklist_item_id' => $item->id, 'answer' => true, 'quantity' => $quantity],
            ],
        ])
        ->assertSessionHasErrors('responses.0.quantity');

    expect(PackageChecklistResponse::query()
        ->where('checklist_item_id', $item->id)
        ->exists())->toBeFalse();
})->with([0, -1, 10000, 2.5]);

it('does not block package submission when a checklist item carries a quantity', function (): void {
    $market = Market::factory()->create();
    $project = Project::factory()->create(['market_id' => $market->id]);
    $template = Template::factory()->create(['market_id' => $market->id]);
    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
        'status' => PackageStatus::DRAFT,
    ]);

    PackageStage::factory()->create([
        'closeout_package_id' => $package->id,
        'stage' => 'post_completion',
        'status' => StageStatus::SUBMITTED,
    ]);

    $item = ChecklistItem::factory()->create(['template_id' => $template->id]);

    PackageChecklistResponse::factory()->create([
        'closeout_package_id' => $package->id,
        'checklist_item_id' => $item->id,
        'answer' => true,
        'quantity' => 4,
    ]);

    expect(fn () => app(SubmitPackageAction::class)->execute($package, User::factory()->create()))
        ->not->toThrow(ChecklistIncompleteException::class);
});
