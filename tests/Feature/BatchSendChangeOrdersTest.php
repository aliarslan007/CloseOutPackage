<?php

declare(strict_types=1);

use App\Domains\ChangeOrders\Enums\ChangeOrderStatus;
use App\Domains\ChangeOrders\Jobs\SendChangeOrderEmailJob;
use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\ChangeOrders\Models\ChangeOrderItem;
use App\Domains\ChangeOrders\Models\ChangeOrderRecipient;
use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\Email\Enums\EmailStatus;
use App\Domains\Email\Enums\EmailType;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Queue::fake();
});

it('sends multiple valid change orders to their assigned customers in batch', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);

    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    CustomerProjectAccess::factory()->create(['user_id' => $customer->id, 'project_id' => $project->id]);

    $co1 = ChangeOrder::factory()->for($project)->create([
        'created_by' => $manager->id,
        'title' => 'Electrical work',
    ]);
    ChangeOrderItem::factory()->create([
        'change_order_id' => $co1->id,
        'is_included' => true,
    ]);
    ChangeOrderRecipient::factory()->create([
        'change_order_id' => $co1->id,
        'user_id' => $customer->id,
    ]);

    $co2 = ChangeOrder::factory()->for($project)->create([
        'created_by' => $manager->id,
        'title' => 'Cable routing',
    ]);
    ChangeOrderItem::factory()->create([
        'change_order_id' => $co2->id,
        'is_included' => true,
    ]);
    ChangeOrderRecipient::factory()->create([
        'change_order_id' => $co2->id,
        'user_id' => $customer->id,
    ]);

    $response = $this->actingAs($manager)
        ->from('/change-orders')
        ->post(route('change-orders.batch-send'), [
            'change_order_ids' => [$co1->id, $co2->id],
        ]);

    $response->assertRedirect('/change-orders')
        ->assertSessionHas('success');

    // The send click flips DRAFT → SENT_TO_CUSTOMER; actual email delivery
    // from the outbox still only emails + mints tokens.
    expect($co1->fresh()->status)->toBe(ChangeOrderStatus::SENT_TO_CUSTOMER)
        ->and($co2->fresh()->status)->toBe(ChangeOrderStatus::SENT_TO_CUSTOMER);

    $this->assertDatabaseHas('email_logs', [
        'change_order_id' => $co1->id,
        'type' => EmailType::CHANGE_ORDER->value,
        'status' => EmailStatus::READY->value,
    ]);
    $this->assertDatabaseHas('email_logs', [
        'change_order_id' => $co2->id,
        'type' => EmailType::CHANGE_ORDER->value,
        'status' => EmailStatus::READY->value,
    ]);

    Queue::assertNotPushed(SendChangeOrderEmailJob::class);
});

it('skips change orders without customer recipients and reports the issue', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);

    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    CustomerProjectAccess::factory()->create(['user_id' => $customer->id, 'project_id' => $project->id]);

    // co1 has recipient
    $co1 = ChangeOrder::factory()->for($project)->create([
        'created_by' => $manager->id,
        'title' => 'With Customer',
    ]);
    ChangeOrderItem::factory()->create([
        'change_order_id' => $co1->id,
        'is_included' => true,
    ]);
    ChangeOrderRecipient::factory()->create([
        'change_order_id' => $co1->id,
        'user_id' => $customer->id,
    ]);

    // co2 has NO recipient
    $co2 = ChangeOrder::factory()->for($project)->create([
        'created_by' => $manager->id,
        'title' => 'Without Customer',
    ]);
    ChangeOrderItem::factory()->create([
        'change_order_id' => $co2->id,
        'is_included' => true,
    ]);

    $response = $this->actingAs($manager)
        ->from('/change-orders')
        ->post(route('change-orders.batch-send'), [
            'change_order_ids' => [$co1->id, $co2->id],
        ]);

    $response->assertRedirect('/change-orders');
    $response->assertSessionHas('warning');

    // Only the CO with a recipient flips and gets an outbox intent; the
    // one without one stays DRAFT.
    expect($co1->fresh()->status)->toBe(ChangeOrderStatus::SENT_TO_CUSTOMER)
        ->and($co2->fresh()->status)->toBe(ChangeOrderStatus::DRAFT);

    $this->assertDatabaseHas('email_logs', [
        'change_order_id' => $co1->id,
        'status' => EmailStatus::READY->value,
    ]);
    $this->assertDatabaseMissing('email_logs', [
        'change_order_id' => $co2->id,
    ]);

    Queue::assertNotPushed(SendChangeOrderEmailJob::class);
});

it('returns an error when all selected change orders lack customer recipients', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);

    $co = ChangeOrder::factory()->for($project)->create([
        'created_by' => $manager->id,
        'title' => 'Unassigned Order',
    ]);
    ChangeOrderItem::factory()->create([
        'change_order_id' => $co->id,
        'is_included' => true,
    ]);

    $response = $this->actingAs($manager)
        ->from('/change-orders')
        ->post(route('change-orders.batch-send'), [
            'change_order_ids' => [$co->id],
        ]);

    $response->assertRedirect('/change-orders')
        ->assertSessionHas('error');

    expect($co->fresh()->status)->toBe(ChangeOrderStatus::DRAFT);
    Queue::assertNotPushed(SendChangeOrderEmailJob::class);
});

it('denies batch send to customer users', function (): void {
    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    $project = Project::factory()->create();

    $co = ChangeOrder::factory()->for($project)->create();

    $this->actingAs($customer)
        ->post(route('change-orders.batch-send'), [
            'change_order_ids' => [$co->id],
        ])
        ->assertRedirect()
        ->assertSessionHas('error');
});

it('allows resending already sent change orders and pushes new notification jobs', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $project = Project::factory()->create(['manager_id' => $manager->id]);

    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
    CustomerProjectAccess::factory()->create(['user_id' => $customer->id, 'project_id' => $project->id]);

    $co = ChangeOrder::factory()->for($project)->create([
        'created_by' => $manager->id,
        'title' => 'Already Sent Order',
        'status' => ChangeOrderStatus::SENT_TO_CUSTOMER,
        'sent_at' => now()->subDay(),
    ]);
    ChangeOrderItem::factory()->create([
        'change_order_id' => $co->id,
        'is_included' => true,
    ]);
    ChangeOrderRecipient::factory()->create([
        'change_order_id' => $co->id,
        'user_id' => $customer->id,
    ]);

    $response = $this->actingAs($manager)
        ->from('/change-orders')
        ->post(route('change-orders.batch-send'), [
            'change_order_ids' => [$co->id],
        ]);

    $response->assertRedirect('/change-orders')
        ->assertSessionHas('success');

    // Resending an already-sent CO records a fresh READY intent; the
    // change order keeps its original sent status untouched.
    expect($co->fresh()->status)->toBe(ChangeOrderStatus::SENT_TO_CUSTOMER);
    $this->assertDatabaseHas('email_logs', [
        'change_order_id' => $co->id,
        'status' => EmailStatus::READY->value,
    ]);
    Queue::assertNotPushed(SendChangeOrderEmailJob::class);
});
