<?php

use App\Domains\ChangeOrders\Enums\ChangeOrderStatus;
use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\ChangeOrders\Models\ChangeOrderItem;
use App\Domains\ChangeOrders\Models\ChangeOrderPhoto;
use App\Domains\ChangeOrders\Models\ChangeOrderRecipient;
use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CustomerPortal\Actions\GrantCustomerProjectAccessAction;
use App\Domains\CustomerPortal\Actions\RevokeCustomerProjectAccessAction;
use App\Domains\CustomerPortal\DTOs\GrantCustomerAccessDTO;
use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\Projects\Models\IssueHistory;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Storage::fake('private');
});

it('strictly blocks all project-related URLs and actions when access is revoked from a customer', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);

    $project = Project::factory()->create(['manager_id' => $manager->id]);

    // Grant access initially
    app(GrantCustomerProjectAccessAction::class)->execute(new GrantCustomerAccessDTO(
        userId: $customer->id,
        projectIds: [$project->id],
        grantedBy: $admin->id,
    ));

    // Create closeout package in approved status
    $package = CloseoutPackage::factory()->for($project)->create([
        'status' => PackageStatus::APPROVED,
        'created_by' => $manager->id,
    ]);

    // Create an issue on the project
    $issue = IssueHistory::factory()->for($project)->create([
        'reported_by' => $manager->id,
    ]);

    // Create a change order and add customer as recipient
    $changeOrder = ChangeOrder::factory()->for($project)->create([
        'created_by' => $manager->id,
        'status' => ChangeOrderStatus::SENT_TO_CUSTOMER,
    ]);
    $item = ChangeOrderItem::factory()->for($changeOrder)->create();

    $rawToken = ChangeOrderRecipient::generateToken();
    $recipient = ChangeOrderRecipient::factory()->for($changeOrder)->create([
        'user_id' => $customer->id,
        'token_hash' => ChangeOrderRecipient::hashToken($rawToken),
        'token_expires_at' => now()->addDays(30),
    ]);

    // Attach a photo to the change order
    Storage::disk('private')->put('change-orders/test.jpg', 'fake-image-bytes');
    $changeOrderPhoto = ChangeOrderPhoto::create([
        'change_order_id' => $changeOrder->id,
        'path' => 'change-orders/test.jpg',
        'original_name' => 'test.jpg',
        'mime_type' => 'image/jpeg',
        'size_bytes' => 100,
        'uploaded_by' => $manager->id,
    ]);

    // VERIFY ACTIVE ACCESS: customer can access all pages
    $this->actingAs($customer)->get(route('portal.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('projects', 1));
    $this->actingAs($customer)->get(route('portal.projects.show', $project))->assertOk();
    $this->actingAs($customer)->get(route('portal.packages.show', $package))->assertRedirect();
    $this->actingAs($customer)->get(route('projects.show', $project))->assertOk();
    $this->actingAs($customer)->get(route('projects.issues.index', $project))->assertOk();
    $this->actingAs($customer)->get(route('projects.change-orders.index', $project))->assertOk();
    $this->actingAs($customer)->get(route('customer.change-orders.show', [$project, $changeOrder]))->assertOk();
    $this->actingAs($customer)->get(route('change-orders.photos.stream', $changeOrderPhoto))->assertOk();
    $this->get(route('change-orders.token.show', ['token' => $rawToken]))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('isExpired', false));

    // NOW REVOKE CUSTOMER ACCESS
    app(RevokeCustomerProjectAccessAction::class)->execute($customer, $project, $admin);

    expect(CustomerProjectAccess::query()->where('user_id', $customer->id)->where('project_id', $project->id)->exists())->toBeFalse();

    // 1. Customer portal index: project is no longer visible
    $this->actingAs($customer)->get(route('portal.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('projects', 0));

    // 2. Customer portal project show: 404
    $this->actingAs($customer)->get(route('portal.projects.show', $project))->assertNotFound();

    // 3. Customer portal package show: 404
    $this->actingAs($customer)->get(route('portal.packages.show', $package))->assertNotFound();

    // 4. Project show page: 403 Forbidden
    $this->actingAs($customer)->get(route('projects.show', $project))->assertForbidden();

    // 5. Packages index: 403 Forbidden
    $this->actingAs($customer)->get(route('packages.index', $project))->assertForbidden();

    // 6. Packages show: 403 Forbidden
    $this->actingAs($customer)->get(route('packages.show', $package))->assertForbidden();

    // 7. Issues index: 403 Forbidden
    $this->actingAs($customer)->get(route('projects.issues.index', $project))->assertForbidden();

    // 8. Change orders project index: 403 Forbidden
    $this->actingAs($customer)->get(route('projects.change-orders.index', $project))->assertForbidden();

    // 9. Change orders global index: change order is not listed
    $this->actingAs($customer)->get(route('change-orders.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('changeOrders', 0));

    // 10. Change order show: 403 Forbidden
    $this->actingAs($customer)->get(route('customer.change-orders.show', [$project, $changeOrder]))->assertForbidden();
    $this->actingAs($customer)->get(route('projects.change-orders.show', [$project, $changeOrder]))->assertForbidden();

    // 11. Customer portal change order decision is denied (redirects with error or 403 on JSON)
    $this->actingAs($customer)->post(route('customer.change-orders.decide', [$project, $changeOrder]), [
        'decisions' => [['item_id' => $item->id, 'decision' => 'approved']],
    ])->assertRedirect()->assertSessionHas('error');

    $this->actingAs($customer)->postJson(route('customer.change-orders.decide', [$project, $changeOrder]), [
        'decisions' => [['item_id' => $item->id, 'decision' => 'approved']],
    ])->assertForbidden();

    // 12. Change order photo stream: 403 Forbidden
    $this->actingAs($customer)->get(route('change-orders.photos.stream', $changeOrderPhoto))->assertForbidden();

    // 13. Emailed token link is expired
    $this->get(route('change-orders.token.show', ['token' => $rawToken]))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('ChangeOrders/TokenResponse')
            ->where('isExpired', true)
        );

    // 14. Emailed token decision submission is rejected
    $this->post(route('change-orders.token.decide', ['token' => $rawToken]), [
        'decisions' => [['item_id' => $item->id, 'decision' => 'approved']],
    ])->assertRedirect(route('change-orders.token.show', ['token' => $rawToken]))
        ->assertSessionHas('error');
});
