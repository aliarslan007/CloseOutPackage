<?php

use App\Domains\ChangeOrders\Enums\ChangeOrderStatus;
use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\ChangeOrders\Models\ChangeOrderRecipient;
use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\Customers\Models\Customer;
use App\Domains\Markets\Models\Market;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
});

function changeOrderPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Tree Removal',
        'description' => 'Extra work outside scope',
        'items' => [
            [
                'description' => 'Tree removal by Acme',
                'vendor_name' => 'Acme Tree Co.',
                'category' => 'Third-party service',
                'amount' => 350.00,
                'is_included' => true,
            ],
            [
                'description' => 'Permit fees',
                'vendor_name' => null,
                'category' => 'Other',
                'amount' => 75.00,
                'is_included' => true,
            ],
            [
                'description' => 'Internal labor (not billed)',
                'vendor_name' => null,
                'category' => 'Labor',
                'amount' => 500.00,
                'is_included' => false,
            ],
        ],
        'recipient_ids' => [],
    ], $overrides);
}

it('creates a change order as a manager with computed total of included items', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);

    $response = $this->actingAs($manager)
        ->post(route('projects.change-orders.store', $project), changeOrderPayload());

    $response->assertRedirect(route('projects.change-orders.index', $project));

    $changeOrder = ChangeOrder::query()->where('project_id', $project->id)->firstOrFail();
    expect($changeOrder->title)->toBe('Tree Removal')
        ->and($changeOrder->status)->toBe(ChangeOrderStatus::DRAFT)
        // only the 2 included items (350 + 75), not the excluded 500
        ->and((float) $changeOrder->total_amount)->toBe(425.00)
        ->and($changeOrder->items()->count())->toBe(3)
        ->and($changeOrder->items()->where('is_included', true)->count())->toBe(2);
});

it('denies change order creation to field supervisors', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $supervisor = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $project->fieldSupervisors()->attach($supervisor->id);

    $this->actingAs($supervisor)
        ->post(route('projects.change-orders.store', $project), changeOrderPayload())
        ->assertRedirect()
        ->assertSessionHas('error');
});

it('denies change order creation to customers', function (): void {
    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    $project = Project::factory()->create();
    CustomerProjectAccess::factory()->create(['user_id' => $customer->id, 'project_id' => $project->id]);

    $this->actingAs($customer)
        ->post(route('projects.change-orders.store', $project), changeOrderPayload())
        ->assertRedirect()
        ->assertSessionHas('error');
});

it('validates required fields on create', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);

    $this->actingAs($manager)
        ->post(route('projects.change-orders.store', $project), [
            'title' => '',
            'items' => [],
        ])
        ->assertSessionHasErrors(['title', 'items']);
});

it('enforces the shared cover note limit on create and update', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $draft = ChangeOrder::factory()->for($project)->create(['created_by' => $manager->id]);

    // Pins the product decision, not just the enforcement: the UI counter and
    // the textarea cap both read this value.
    expect(ChangeOrder::MAX_DESCRIPTION_LENGTH)->toBe(5000);

    $atLimit = str_repeat('a', ChangeOrder::MAX_DESCRIPTION_LENGTH);

    // The UI used to cap input at 1000; the server accepts the full limit.
    $this->actingAs($manager)
        ->post(route('projects.change-orders.store', $project), changeOrderPayload(['description' => $atLimit]))
        ->assertSessionHasNoErrors();

    $created = ChangeOrder::query()->where('project_id', $project->id)->latest('id')->firstOrFail();
    expect($created->description)->toBe($atLimit);

    $this->actingAs($manager)
        ->put(route('projects.change-orders.update', [$project, $draft]), changeOrderPayload(['description' => $atLimit]))
        ->assertSessionHasNoErrors();

    expect($draft->fresh()->description)->toBe($atLimit);

    // One character over is rejected on both paths.
    $overLimit = $atLimit.'a';

    $this->actingAs($manager)
        ->put(route('projects.change-orders.update', [$project, $draft]), changeOrderPayload(['description' => $overLimit]))
        ->assertSessionHasErrors('description');

    $this->actingAs($manager)
        ->post(route('projects.change-orders.store', $project), changeOrderPayload(['description' => $overLimit]))
        ->assertSessionHasErrors('description');
});

it('enforces the shared title limit on create and update', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $draft = ChangeOrder::factory()->for($project)->create(['created_by' => $manager->id]);

    // Pins the product decision, not just the enforcement: the UI counter and
    // the input cap both read this value. The `title` column stays varchar(255),
    // so this is the binding contract rather than a column mirror.
    expect(ChangeOrder::MAX_TITLE_LENGTH)->toBe(100);

    $atLimit = str_repeat('a', ChangeOrder::MAX_TITLE_LENGTH);

    $this->actingAs($manager)
        ->post(route('projects.change-orders.store', $project), changeOrderPayload(['title' => $atLimit]))
        ->assertSessionHasNoErrors();

    expect(ChangeOrder::query()->where('project_id', $project->id)->latest('id')->firstOrFail()->title)
        ->toBe($atLimit);

    $this->actingAs($manager)
        ->put(route('projects.change-orders.update', [$project, $draft]), changeOrderPayload(['title' => $atLimit]))
        ->assertSessionHasNoErrors();

    expect($draft->fresh()->title)->toBe($atLimit);

    // One character over is rejected on both paths.
    $overLimit = $atLimit.'a';

    $this->actingAs($manager)
        ->put(route('projects.change-orders.update', [$project, $draft]), changeOrderPayload(['title' => $overLimit]))
        ->assertSessionHasErrors('title');

    $this->actingAs($manager)
        ->post(route('projects.change-orders.store', $project), changeOrderPayload(['title' => $overLimit]))
        ->assertSessionHasErrors('title');
});

it('enforces the shared item description limit on create and update', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $draft = ChangeOrder::factory()->for($project)->create(['created_by' => $manager->id]);

    // Pins the product decision and proves the bare 2000 literal is gone from the
    // rule: the per-item counter and input cap both read this constant.
    expect(ChangeOrder::MAX_ITEM_DESCRIPTION_LENGTH)->toBe(2000);

    $atLimit = str_repeat('a', ChangeOrder::MAX_ITEM_DESCRIPTION_LENGTH);

    $this->actingAs($manager)
        ->post(route('projects.change-orders.store', $project), changeOrderPayload([
            'items' => [['description' => $atLimit, 'amount' => 100]],
        ]))
        ->assertSessionHasNoErrors();

    expect(
        ChangeOrder::query()
            ->where('project_id', $project->id)
            ->latest('id')
            ->firstOrFail()
            ->items->first()
            ->description
    )->toBe($atLimit);

    // One character over is rejected on both the create and the update path.
    $overLimit = $atLimit.'a';

    $this->actingAs($manager)
        ->put(route('projects.change-orders.update', [$project, $draft]), changeOrderPayload([
            'items' => [['description' => $overLimit, 'amount' => 100]],
        ]))
        ->assertSessionHasErrors('items.0.description');

    $this->actingAs($manager)
        ->post(route('projects.change-orders.store', $project), changeOrderPayload([
            'items' => [['description' => $overLimit, 'amount' => 100]],
        ]))
        ->assertSessionHasErrors('items.0.description');
});

it('stores multiple vendors per itemized cost line and rejects more than the shared cap', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);

    // Pins the product decision: the UI chip counter and the server rule both
    // read this constant.
    expect(ChangeOrder::MAX_ITEM_VENDORS)->toBe(5);

    $fiveVendors = ['Acme Tree Co.', 'Weld Corp', 'Permit Bros', 'A&B Labor', 'Heavy Equipment LLC'];

    $this->actingAs($manager)
        ->post(route('projects.change-orders.store', $project), changeOrderPayload([
            'items' => [[
                'description' => 'Multi-vendor line',
                'vendor_name' => $fiveVendors,
                'category' => 'Third-party service',
                'amount' => 100.00,
                'is_included' => true,
            ]],
        ]))
        ->assertSessionHasNoErrors();

    $created = ChangeOrder::query()->where('project_id', $project->id)->latest('id')->firstOrFail();
    expect($created->items()->firstOrFail()->vendor_name)->toBe($fiveVendors);

    // One over the cap is rejected on create.
    $this->actingAs($manager)
        ->post(route('projects.change-orders.store', $project), changeOrderPayload([
            'items' => [[
                'description' => 'Too many vendors',
                'vendor_name' => [...$fiveVendors, 'Sixth Co'],
                'category' => 'Other',
                'amount' => 10.00,
                'is_included' => true,
            ]],
        ]))
        ->assertSessionHasErrors('items.0.vendor_name');

    // Legacy plain-string payloads (old tests / clients) still work as one vendor.
    $this->actingAs($manager)
        ->post(route('projects.change-orders.store', $project), changeOrderPayload())
        ->assertSessionHasNoErrors();

    $legacy = ChangeOrder::query()->where('project_id', $project->id)->latest('id')->firstOrFail();
    expect($legacy->items()->whereNotNull('vendor_name')->firstOrFail()->vendor_name)
        ->toBe(['Acme Tree Co.']);
});

it('rejects recipients without customer access to the project', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $outsider = User::factory()->create()->assignRole(Role::CUSTOMER->value);

    $this->actingAs($manager)
        ->post(route('projects.change-orders.store', $project), changeOrderPayload([
            'recipient_ids' => [$outsider->id],
        ]))
        ->assertSessionHasErrors('recipient_ids.0');
});

it('updates a draft but locks a sent change order', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $draft = ChangeOrder::factory()->for($project)->create(['created_by' => $manager->id]);
    $sent = ChangeOrder::factory()->sent()->for($project)->create(['created_by' => $manager->id]);

    $this->actingAs($manager)
        ->put(route('projects.change-orders.update', [$project, $draft]), changeOrderPayload([
            'title' => 'Updated Draft Title',
        ]))
        ->assertRedirect();

    expect($draft->fresh()->title)->toBe('Updated Draft Title');

    $this->actingAs($manager)
        ->put(route('projects.change-orders.update', [$project, $sent]), changeOrderPayload())
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($sent->fresh()->title)->not->toBe('Updated Draft Title');
});

it('deletes drafts only', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $draft = ChangeOrder::factory()->for($project)->create(['created_by' => $manager->id]);
    $sent = ChangeOrder::factory()->sent()->for($project)->create(['created_by' => $manager->id]);

    $this->actingAs($manager)
        ->delete(route('projects.change-orders.destroy', [$project, $sent]))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->actingAs($manager)
        ->delete(route('projects.change-orders.destroy', [$project, $draft]))
        ->assertRedirect(route('projects.change-orders.index', $project))
        ->assertSessionHas('success');

    // fresh() bypasses the SoftDeletes scope — assert trashed() instead.
    expect($draft->fresh()->trashed())->toBeTrue()
        ->and($sent->fresh()->trashed())->toBeFalse();
});

it('lists change orders for a project newest first', function (): void {
    $this->withoutVite();

    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);
    ChangeOrder::factory()->for($project)->create(['created_by' => $manager->id, 'created_at' => now()->subDay()]);

    $this->actingAs($manager)
        ->get(route('projects.change-orders.index', $project))
        ->assertOk();
});

it('scopes recipients to customers with project access and stores rows at draft', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    CustomerProjectAccess::factory()->create(['user_id' => $customer->id, 'project_id' => $project->id]);

    $this->actingAs($manager)
        ->post(route('projects.change-orders.store', $project), changeOrderPayload([
            'recipient_ids' => [$customer->id],
        ]))
        ->assertRedirect();

    $changeOrder = ChangeOrder::query()->where('project_id', $project->id)->firstOrFail();
    $recipient = $changeOrder->recipients()->first();
    // Token is minted at send time (Phase 2) — the draft row only reserves the user.
    expect($changeOrder->recipients()->count())->toBe(1)
        ->and($recipient->user_id)->toBe($customer->id)
        ->and($recipient->token_hash)->toBeNull();
});

it('reorders a project change order list and lists it back in the saved order', function (): void {
    $this->withoutVite();

    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $orders = ChangeOrder::factory()->for($project)->count(3)->create(['created_by' => $manager->id]);

    $this->actingAs($manager)
        ->post(route('projects.change-orders.reorder', $project), [
            'items' => [
                ['id' => $orders[2]->id, 'sort_order' => 0],
                ['id' => $orders[0]->id, 'sort_order' => 1],
                ['id' => $orders[1]->id, 'sort_order' => 2],
            ],
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($project->changeOrders()->orderBy('sort_order')->pluck('id')->all())
        ->toBe([$orders[2]->id, $orders[0]->id, $orders[1]->id]);

    // The manager's page renders the saved order, not the newest-first default.
    $this->actingAs($manager)
        ->get(route('projects.change-orders.index', $project))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Projects/ChangeOrders/Index')
            ->has('changeOrders', 3)
            ->where('changeOrders.0.id', $orders[2]->id)
            ->where('changeOrders.1.id', $orders[0]->id)
            ->where('changeOrders.2.id', $orders[1]->id)
        );
});

it('keeps the all-projects order independent from each project order', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $alpha = Project::factory()->create();
    $beta = Project::factory()->create();
    $alphaOrders = ChangeOrder::factory()->for($alpha)->count(2)->create(['created_by' => $admin->id]);
    $betaOrder = ChangeOrder::factory()->for($beta)->create(['created_by' => $admin->id]);

    // Project page: the newer Alpha change order goes above the older one.
    $this->actingAs($admin)
        ->post(route('projects.change-orders.reorder', $alpha), [
            'items' => [
                ['id' => $alphaOrders[1]->id, 'sort_order' => 0],
                ['id' => $alphaOrders[0]->id, 'sort_order' => 1],
            ],
        ])
        ->assertOk();

    // All-projects page: Beta first, then Alpha's two rows — deliberately not
    // the order Alpha's own page uses.
    $this->actingAs($admin)
        ->post(route('change-orders.reorder'), [
            'items' => [
                ['id' => $betaOrder->id, 'sort_order' => 0],
                ['id' => $alphaOrders[0]->id, 'sort_order' => 1],
                ['id' => $alphaOrders[1]->id, 'sort_order' => 2],
            ],
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    $this->actingAs($admin)
        ->get(route('change-orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ChangeOrders/Index')
            ->has('changeOrders', 3)
            ->where('changeOrders.0.id', $betaOrder->id)
            ->where('changeOrders.1.id', $alphaOrders[0]->id)
            ->where('changeOrders.2.id', $alphaOrders[1]->id)
        );

    // The global drag did not touch Alpha's own saved order.
    $this->actingAs($admin)
        ->get(route('projects.change-orders.index', $alpha))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Projects/ChangeOrders/Index')
            ->has('changeOrders', 2)
            ->where('changeOrders.0.id', $alphaOrders[1]->id)
            ->where('changeOrders.1.id', $alphaOrders[0]->id)
        );
});

it('lets a market manager reorder the global list they can see', function (): void {
    $this->withoutVite();

    $market = Market::factory()->create(['is_active' => true]);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    $myProject = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);

    $mine = ChangeOrder::factory()->for($myProject)->count(3)->create(['created_by' => $manager->id]);
    ChangeOrder::factory()->count(2)->create(); // another market — not this manager's list

    $this->actingAs($manager)
        ->post(route('change-orders.reorder'), [
            'items' => [
                ['id' => $mine[2]->id, 'sort_order' => 0],
                ['id' => $mine[0]->id, 'sort_order' => 1],
                ['id' => $mine[1]->id, 'sort_order' => 2],
            ],
        ])
        ->assertOk();

    expect($mine->pluck('id')->map(fn (int $id): ?int => ChangeOrder::find($id)?->global_sort_order)->all())
        ->toBe([1, 2, 0]);

    // The manager's own list shows the arrangement, and only their market's rows.
    $this->actingAs($manager)
        ->get(route('change-orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ChangeOrders/Index')
            ->has('changeOrders', 3)
            ->where('changeOrders.0.id', $mine[2]->id)
            ->where('changeOrders.1.id', $mine[0]->id)
            ->where('changeOrders.2.id', $mine[1]->id)
        );
});

it('only offers a manager the projects assigned to them when creating a change order', function (): void {
    $this->withoutVite();

    $market = Market::factory()->create(['is_active' => true]);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);

    $myProject = Project::factory()->create(['market_id' => $market->id, 'manager_id' => $manager->id]);
    // Same market, but managed by someone else — must not leak into the picker.
    $otherProject = Project::factory()->create([
        'market_id' => $market->id,
        'manager_id' => User::factory()->create()->id,
    ]);
    Project::factory()->create(['market_id' => $market->id, 'manager_id' => null]);

    $myOrder = ChangeOrder::factory()->for($myProject)->create(['created_by' => $manager->id]);
    ChangeOrder::factory()->for($otherProject)->create();

    $this->actingAs($manager)
        ->get(route('change-orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ChangeOrders/Index')
            ->has('projects', 1)
            ->where('projects.0.id', $myProject->id)
            ->has('changeOrders', 1)
            ->where('changeOrders.0.id', $myOrder->id)
        );
});

it('forbids a manager from creating a change order for a project they do not manage', function (): void {
    $market = Market::factory()->create(['is_active' => true]);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    // In the manager's market, but assigned to another manager.
    $project = Project::factory()->create([
        'market_id' => $market->id,
        'manager_id' => User::factory()->create()->id,
    ]);

    $this->actingAs($manager)
        ->get(route('projects.change-orders.create', $project))
        ->assertForbidden();

    $this->actingAs($manager)
        ->post(route('projects.change-orders.store', $project), changeOrderPayload())
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(ChangeOrder::query()->where('project_id', $project->id)->exists())->toBeFalse();
});

it('rejects reordering change orders that belong to another project', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $mine = ChangeOrder::factory()->for($project)->create(['created_by' => $manager->id]);
    $foreign = ChangeOrder::factory()->create(['created_by' => $manager->id]);

    $this->actingAs($manager)
        ->post(route('projects.change-orders.reorder', $project), [
            'items' => [['id' => $foreign->id, 'sort_order' => 0]],
        ])
        ->assertSessionHasErrors('items.0.id');

    expect($foreign->fresh()->sort_order)->toBeNull()
        ->and($mine->fresh()->sort_order)->toBeNull();
});

it('forbids non-staff roles from reordering change orders', function (string $role): void {
    $order = ChangeOrder::factory()->create();
    $user = User::factory()->create()->assignRole($role);
    $payload = ['items' => [['id' => $order->id, 'sort_order' => 0]]];

    // The axios client sends X-Requested-With, so a denied role gets a 403
    // JSON response rather than the redirect used for plain form posts.
    $this->actingAs($user)
        ->postJson(route('projects.change-orders.reorder', $order->project), $payload)
        ->assertForbidden();

    $this->actingAs($user)
        ->postJson(route('change-orders.reorder'), $payload)
        ->assertForbidden();

    expect($order->fresh()->sort_order)->toBeNull()
        ->and($order->fresh()->global_sort_order)->toBeNull();
})->with([Role::CUSTOMER->value, Role::FIELD_SUPERVISOR->value]);

it('creates a change order with items whose cost is absorbed by SoluComp and excludes them from total_amount', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);

    $payload = [
        'title' => 'Electrical Overhaul with Free Scope',
        'description' => 'Upgrades including free diagnostics',
        'items' => [
            [
                'description' => 'Main breaker replacement',
                'vendor_name' => ['Square D'],
                'category' => 'Materials',
                'amount' => 450.00,
                'is_included' => true,
                'cost_absorbed_by_solucomp' => false,
            ],
            [
                'description' => 'Site safety audit (complimentary)',
                'vendor_name' => ['SoluComp Safety Team'],
                'category' => 'Safety & Compliance',
                'amount' => 200.00,
                'is_included' => true,
                'cost_absorbed_by_solucomp' => true,
            ],
        ],
        'recipient_ids' => [],
    ];

    $response = $this->actingAs($manager)
        ->post(route('projects.change-orders.store', $project), $payload);

    $response->assertRedirect(route('projects.change-orders.index', $project));

    $changeOrder = ChangeOrder::query()->where('project_id', $project->id)->firstOrFail();
    expect($changeOrder->items()->count())->toBe(2)
        // total_amount only includes the billed $450, not the $200 absorbed
        ->and((float) $changeOrder->total_amount)->toBe(450.00);

    $absorbedItem = $changeOrder->items()->where('cost_absorbed_by_solucomp', true)->first();
    expect($absorbedItem)->not->toBeNull()
        ->and($absorbedItem->description)->toBe('Site safety audit (complimentary)')
        ->and($absorbedItem->cost_absorbed_by_solucomp)->toBeTrue();
});

it('creates and updates a change order with change_order_date', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);

    $payload = changeOrderPayload([
        'change_order_date' => '2026-09-23',
    ]);

    $response = $this->actingAs($manager)
        ->post(route('projects.change-orders.store', $project), $payload);

    $response->assertRedirect(route('projects.change-orders.index', $project));

    $changeOrder = ChangeOrder::query()->where('project_id', $project->id)->firstOrFail();
    expect($changeOrder->change_order_date?->toDateString())->toBe('2026-09-23');

    // Update with a new occurrence date
    $updatePayload = array_merge(changeOrderPayload([
        'title' => 'Updated Tree Removal',
        'change_order_date' => '2026-10-15',
    ]), [
        'items' => [
            [
                'id' => $changeOrder->items->first()->id,
                'description' => 'Updated item description',
                'vendor_name' => 'Acme Tree Co.',
                'category' => 'Third-party service',
                'amount' => 350.00,
                'is_included' => true,
                'cost_absorbed_by_solucomp' => false,
            ],
        ],
    ]);

    $this->actingAs($manager)
        ->put(route('projects.change-orders.update', [$project, $changeOrder]), $updatePayload)
        ->assertRedirect(route('projects.change-orders.show', [$project, $changeOrder]));

    $changeOrder->refresh();
    expect($changeOrder->change_order_date?->toDateString())->toBe('2026-10-15');
});

it('validates change_order_date bounds on create', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);

    // Before 2000-01-01
    $this->actingAs($manager)
        ->post(route('projects.change-orders.store', $project), changeOrderPayload([
            'change_order_date' => '1999-12-31',
        ]))
        ->assertSessionHasErrors(['change_order_date']);

    // More than 2 years into the future
    $this->actingAs($manager)
        ->post(route('projects.change-orders.store', $project), changeOrderPayload([
            'change_order_date' => now()->addYears(3)->toDateString(),
        ]))
        ->assertSessionHasErrors(['change_order_date']);
});

it('provides customer users and customer-linked projects on global index', function (): void {
    $this->withoutVite();

    $customer = Customer::factory()->create(['name' => 'Verizon Tech', 'code' => 'VZW']);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $customerUser = User::factory()->create([
        'name' => 'Alice Engineer',
        'email' => 'alice@verizon.test',
        'company' => 'Verizon Tech',
    ])->assignRole(Role::CUSTOMER->value);

    $project = Project::factory()->create([
        'customer_id' => $customer->id,
        'manager_id' => $manager->id,
    ]);
    CustomerProjectAccess::factory()->create([
        'project_id' => $project->id,
        'user_id' => $customerUser->id,
    ]);

    $changeOrder = ChangeOrder::factory()->for($project)->create([
        'created_by' => $manager->id,
    ]);
    ChangeOrderRecipient::factory()->create([
        'change_order_id' => $changeOrder->id,
        'user_id' => $customerUser->id,
    ]);

    $this->actingAs($manager)
        ->get(route('change-orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ChangeOrders/Index')
            ->has('customers', 1)
            ->where('customers.0.id', $customerUser->id)
            ->where('customers.0.name', 'Alice Engineer')
            ->where('customers.0.company', 'Verizon Tech')
            ->where('changeOrders.0.id', $changeOrder->id)
            ->where('changeOrders.0.project.customer.id', $customer->id)
        );
});

it('scopes the global change order list and customer options to a customer role', function (): void {
    // A customer engineer must never see the wider system's change orders, nor
    // the other contacts attached to them — the customer filter is hidden for
    // them, so the option list must be their own account only.
    $this->withoutVite();

    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $customerRecord = Customer::factory()->create(['name' => 'Verizon Tech']);
    $project = Project::factory()->create([
        'manager_id' => $manager->id,
        'customer_id' => $customerRecord->id,
    ]);
    $otherProject = Project::factory()->create([
        'manager_id' => $manager->id,
        'customer_id' => $customerRecord->id,
    ]);

    $engineer = User::factory()->create([
        'name' => 'Alice Engineer',
        'company' => 'Verizon Tech',
    ]);
    $engineer->assignRole(Role::CUSTOMER->value);
    $peer = User::factory()->create(['name' => 'Bob Peer', 'company' => 'Verizon Tech'])
        ->assignRole(Role::CUSTOMER->value);
    $outsider = User::factory()->create(['name' => 'Carol Outsider', 'company' => 'Other Co'])
        ->assignRole(Role::CUSTOMER->value);

    CustomerProjectAccess::factory()->create(['user_id' => $engineer->id, 'project_id' => $project->id]);
    // The peer shares the project; the outsider does not.
    CustomerProjectAccess::factory()->create(['user_id' => $peer->id, 'project_id' => $project->id]);
    CustomerProjectAccess::factory()->create(['user_id' => $outsider->id, 'project_id' => $otherProject->id]);

    $mine = ChangeOrder::factory()->for($project)->create([
        'created_by' => $manager->id,
        'status' => ChangeOrderStatus::SENT_TO_CUSTOMER->value,
    ]);
    $draft = ChangeOrder::factory()->for($project)->create([
        'created_by' => $manager->id,
        'status' => ChangeOrderStatus::DRAFT->value,
    ]);
    $onAnotherProject = ChangeOrder::factory()->for($otherProject)->create([
        'created_by' => $manager->id,
        'status' => ChangeOrderStatus::SENT_TO_CUSTOMER->value,
    ]);
    ChangeOrderRecipient::factory()->create([
        'change_order_id' => $mine->id,
        'user_id' => $engineer->id,
    ]);

    $this->actingAs($engineer)
        ->get(route('change-orders.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ChangeOrders/Index')
            // Their own accessible project only.
            ->has('projects', 1)
            ->where('projects.0.id', $project->id)
            // Sent orders on that project only: no drafts, nothing from the
            // project they have no grant for.
            ->has('changeOrders', 1)
            ->where('changeOrders.0.id', $mine->id)
            // Their own account only: no peer contact, no unrelated customer.
            ->has('customers', 1)
            ->where('customers.0.id', $engineer->id)
        );
});
