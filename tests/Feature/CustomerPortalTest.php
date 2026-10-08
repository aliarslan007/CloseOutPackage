<?php

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageCategorySelection;
use App\Domains\CloseoutPackages\Models\PackageStage;
use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\CustomerPortal\Notifications\CustomerPortalAccessNotification;
use App\Domains\Markets\Models\Market;
use App\Domains\PdfGeneration\Models\PackageRevision;
use App\Domains\Photos\Enums\PhotoStatus;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Models\Template;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    Storage::fake('private');

    $this->fixture = function (): array {
        $market = Market::factory()->create();
        $project = Project::factory()->create(['market_id' => $market->id]);
        $template = Template::factory()->create(['market_id' => $market->id]);
        $customer = User::factory()->create()->assignRole(Role::CUSTOMER->value);
        $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

        return compact('market', 'project', 'template', 'customer', 'admin');
    };

    $this->approvedPackage = function (array $fixture): CloseoutPackage {
        return CloseoutPackage::factory()->create([
            'project_id' => $fixture['project']->id,
            'template_id' => $fixture['template']->id,
            'status' => PackageStatus::APPROVED->value,
        ]);
    };
});

it('lists only granted projects on the customer dashboard', function (): void {
    $this->withoutVite();

    $fixture = ($this->fixture)();
    $granted = Project::factory()->create(['market_id' => $fixture['market']->id]);
    $other = Project::factory()->create();

    CustomerProjectAccess::factory()->create([
        'user_id' => $fixture['customer']->id,
        'project_id' => $granted->id,
    ]);

    $this->actingAs($fixture['customer'])
        ->get(route('portal.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customer/Portal/Index')
            ->has('projects', 1)
            ->where('projects.0.id', $granted->id));
});

it('exposes progress and package counts per project on the portal index', function (): void {
    $this->withoutVite();

    $fixture = ($this->fixture)();
    ($this->approvedPackage)($fixture);
    CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
        'status' => PackageStatus::REJECTED->value,
    ]);
    // A non-rejected package with an active flagged photo also counts as rework.
    $flagged = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
        'status' => PackageStatus::UNDER_REVIEW->value,
    ]);
    $stage = PackageStage::factory()->create(['closeout_package_id' => $flagged->id]);
    $selection = PackageCategorySelection::factory()->create(['package_stage_id' => $stage->id]);
    Photo::factory()->create([
        'closeout_package_id' => $flagged->id,
        'package_stage_id' => $stage->id,
        'package_category_selection_id' => $selection->id,
        'status' => PhotoStatus::FLAGGED->value,
    ]);

    CustomerProjectAccess::factory()->create([
        'user_id' => $fixture['customer']->id,
        'project_id' => $fixture['project']->id,
    ]);

    $this->actingAs($fixture['customer'])
        ->get(route('portal.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customer/Portal/Index')
            ->where('projects.0.total_packages_count', 3)
            ->where('projects.0.approved_packages_count', 1)
            ->where('projects.0.rework_packages_count', 2));
});

it('returns 404 for a project the customer was never granted', function (): void {
    $fixture = ($this->fixture)();

    $this->actingAs($fixture['customer'])
        ->get(route('portal.projects.show', $fixture['project']))
        ->assertNotFound();
});

it('returns 403 with a friendly message for expired access', function (): void {
    $fixture = ($this->fixture)();

    CustomerProjectAccess::factory()->create([
        'user_id' => $fixture['customer']->id,
        'project_id' => $fixture['project']->id,
        'expires_at' => now()->subDay(),
    ]);

    $this->actingAs($fixture['customer'])
        ->get(route('portal.projects.show', $fixture['project']))
        ->assertForbidden()
        ->assertSee('Your access to this project has expired');
});

it('redirects a package link to the project view with that package selected', function (): void {
    $fixture = ($this->fixture)();
    $package = ($this->approvedPackage)($fixture);

    CustomerProjectAccess::factory()->create([
        'user_id' => $fixture['customer']->id,
        'project_id' => $fixture['project']->id,
    ]);

    $this->actingAs($fixture['customer'])
        ->get(route('portal.packages.show', $package))
        ->assertRedirect(route('portal.projects.show', [
            'project' => $fixture['project']->getRouteKey(),
            'package' => $package->getRouteKey(),
        ]));
});

it('exposes every approved package of a project to an authorized customer', function (): void {
    $this->withoutVite();

    $fixture = ($this->fixture)();
    $first = ($this->approvedPackage)($fixture);
    $second = ($this->approvedPackage)($fixture);

    CustomerProjectAccess::factory()->create([
        'user_id' => $fixture['customer']->id,
        'project_id' => $fixture['project']->id,
    ]);

    Photo::factory()->create([
        'closeout_package_id' => $first->id,
        'is_approved' => true,
        'status' => PhotoStatus::APPROVED->value,
    ]);
    Photo::factory()->create([
        'closeout_package_id' => $second->id,
        'is_approved' => true,
        'status' => PhotoStatus::APPROVED->value,
    ]);

    $this->actingAs($fixture['customer'])
        ->get(route('portal.projects.show', $fixture['project']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customer/Portal/Projects/Show')
            ->has('project.packages', 2)
            ->where('project.packages.0.id', $first->id)
            ->where('project.packages.1.id', $second->id)
            ->has('project.packages.0.photos', 1)
            ->has('project.packages.1.photos', 1));
});

it('forbids viewing a draft package in an accessible project', function (): void {
    $fixture = ($this->fixture)();
    $package = CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
        'status' => PackageStatus::DRAFT->value,
    ]);

    CustomerProjectAccess::factory()->create([
        'user_id' => $fixture['customer']->id,
        'project_id' => $fixture['project']->id,
    ]);

    $this->actingAs($fixture['customer'])
        ->get(route('portal.packages.show', $package))
        ->assertNotFound();
});

it('does not expose draft packages to customers on the project page', function (): void {
    $this->withoutVite();

    $fixture = ($this->fixture)();
    $approved = ($this->approvedPackage)($fixture);
    CloseoutPackage::factory()->create([
        'project_id' => $fixture['project']->id,
        'template_id' => $fixture['template']->id,
        'status' => PackageStatus::DRAFT->value,
    ]);

    CustomerProjectAccess::factory()->create([
        'user_id' => $fixture['customer']->id,
        'project_id' => $fixture['project']->id,
    ]);

    $this->actingAs($fixture['customer'])
        ->get(route('portal.projects.show', $fixture['project']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customer/Portal/Projects/Show')
            ->has('project.packages', 1)
            ->where('project.packages.0.id', $approved->id));
});

it('exposes only approved photos of approved packages to customers on the project page', function (): void {
    $this->withoutVite();

    $fixture = ($this->fixture)();
    $package = ($this->approvedPackage)($fixture);

    CustomerProjectAccess::factory()->create([
        'user_id' => $fixture['customer']->id,
        'project_id' => $fixture['project']->id,
    ]);

    $approved = Photo::factory()->create([
        'closeout_package_id' => $package->id,
        'is_approved' => true,
        'status' => PhotoStatus::APPROVED->value,
    ]);
    Photo::factory()->create([
        'closeout_package_id' => $package->id,
        'is_approved' => null,
        'status' => PhotoStatus::PENDING->value,
    ]);
    Photo::factory()->create([
        'closeout_package_id' => $package->id,
        'replaced_at' => now(),
    ]);

    $this->actingAs($fixture['customer'])
        ->get(route('portal.projects.show', $fixture['project']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customer/Portal/Projects/Show')
            ->has('project.packages.0.photos', 1)
            ->where('project.packages.0.photos.0.id', $approved->id));
});

it('exposes per-photo notes to customers on the project page', function (): void {
    $this->withoutVite();

    $fixture = ($this->fixture)();
    $package = ($this->approvedPackage)($fixture);

    CustomerProjectAccess::factory()->create([
        'user_id' => $fixture['customer']->id,
        'project_id' => $fixture['project']->id,
    ]);

    Photo::factory()->create([
        'closeout_package_id' => $package->id,
        'is_approved' => true,
        'status' => PhotoStatus::APPROVED->value,
        'notes' => 'Grounding cable landed on the busbar',
    ]);

    $this->actingAs($fixture['customer'])
        ->get(route('portal.projects.show', $fixture['project']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customer/Portal/Projects/Show')
            ->where('project.packages.0.photos.0.notes', 'Grounding cable landed on the busbar'));
});

it('serves approved photo images to an authorized customer and forbids pending photos', function (): void {
    $fixture = ($this->fixture)();
    $package = ($this->approvedPackage)($fixture);

    CustomerProjectAccess::factory()->create([
        'user_id' => $fixture['customer']->id,
        'project_id' => $fixture['project']->id,
    ]);

    $approved = Photo::factory()->create([
        'closeout_package_id' => $package->id,
        'is_approved' => true,
        'status' => PhotoStatus::APPROVED->value,
    ]);
    // Real 1x1 PNG so medialibrary can generate the thumbnail conversion.
    $approved->addMediaFromBase64('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')
        ->usingFileName('approved.png')
        ->toMediaCollection(Photo::COLLECTION);

    $pending = Photo::factory()->create([
        'closeout_package_id' => $package->id,
        'is_approved' => null,
        'status' => PhotoStatus::PENDING->value,
    ]);
    $pending->addMediaFromBase64('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==')
        ->usingFileName('pending.png')
        ->toMediaCollection(Photo::COLLECTION);

    $this->actingAs($fixture['customer'])
        ->get(route('photos.signed-url', [$package, $approved]))
        ->assertOk();

    $this->actingAs($fixture['customer'])
        ->get(route('photos.signed-url', [$package, $pending]))
        ->assertForbidden();
});

it('forbids customers from fetching photos of ungranted projects', function (): void {
    $fixture = ($this->fixture)();
    $package = ($this->approvedPackage)($fixture);

    $photo = Photo::factory()->create([
        'closeout_package_id' => $package->id,
        'status' => PhotoStatus::PENDING->value,
    ]);

    $this->actingAs($fixture['customer'])
        ->get(route('photos.signed-url', [$package, $photo]))
        ->assertForbidden();
});

it('downloads the final PDF for an authorized customer', function (): void {
    $fixture = ($this->fixture)();
    $package = ($this->approvedPackage)($fixture);
    $path = 'pdfs/'.$package->id.'/final_R1_1.pdf';

    Storage::disk('private')->put($path, 'pdf-binary');

    PackageRevision::factory()->create([
        'closeout_package_id' => $package->id,
        'storage_path' => $path,
    ]);

    CustomerProjectAccess::factory()->create([
        'user_id' => $fixture['customer']->id,
        'project_id' => $fixture['project']->id,
    ]);

    $this->actingAs($fixture['customer'])
        ->get(route('portal.packages.pdf', $package))
        ->assertOk();
});

it('reports when the PDF is not yet available', function (): void {
    $fixture = ($this->fixture)();
    $package = ($this->approvedPackage)($fixture);

    CustomerProjectAccess::factory()->create([
        'user_id' => $fixture['customer']->id,
        'project_id' => $fixture['project']->id,
    ]);

    $this->actingAs($fixture['customer'])
        ->from(route('portal.index'))
        ->get(route('portal.packages.pdf', $package))
        ->assertRedirect(route('portal.index'))
        ->assertSessionHas('info', 'PDF not yet available.');
});

it('grants customer access as admin and notifies the customer', function (): void {
    Notification::fake();

    $fixture = ($this->fixture)();

    $this->actingAs($fixture['admin'])
        ->post(route('admin.customers.access.store', $fixture['customer']), [
            'user_id' => $fixture['customer']->id,
            'project_id' => $fixture['project']->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('customer_project_access', [
        'user_id' => $fixture['customer']->id,
        'project_id' => $fixture['project']->id,
        'granted_by' => $fixture['admin']->id,
    ]);

    Notification::assertSentTo($fixture['customer'], CustomerPortalAccessNotification::class);
});

it('grants multiple projects in a single request', function (): void {
    Notification::fake();

    $fixture = ($this->fixture)();
    $second = Project::factory()->create(['market_id' => $fixture['market']->id]);

    $this->actingAs($fixture['admin'])
        ->post(route('admin.customers.access.store', $fixture['customer']), [
            'user_id' => $fixture['customer']->id,
            'project_ids' => [$fixture['project']->id, $second->id],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('customer_project_access', [
        'user_id' => $fixture['customer']->id,
        'project_id' => $fixture['project']->id,
    ]);
    $this->assertDatabaseHas('customer_project_access', [
        'user_id' => $fixture['customer']->id,
        'project_id' => $second->id,
    ]);

    Notification::assertSentTo($fixture['customer'], CustomerPortalAccessNotification::class);
});

it('rejects granting access to a non-customer user', function (): void {
    $fixture = ($this->fixture)();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);

    $this->actingAs($fixture['admin'])
        ->post(route('admin.customers.access.store', $manager), [
            'user_id' => $manager->id,
            'project_id' => $fixture['project']->id,
        ])
        ->assertSessionHasErrors('user_id');
});

it('revokes customer access', function (): void {
    $fixture = ($this->fixture)();
    $access = CustomerProjectAccess::factory()->create([
        'user_id' => $fixture['customer']->id,
        'project_id' => $fixture['project']->id,
        'granted_by' => $fixture['admin']->id,
    ]);

    $this->actingAs($fixture['admin'])
        ->delete(route('admin.customers.access.destroy', [$fixture['customer'], $fixture['project']]), [
            'user_id' => $fixture['customer']->id,
            'project_id' => $fixture['project']->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(CustomerProjectAccess::find($access->id))->toBeNull();
});

it('hides customer accesses whose project was deleted from the admin listing', function (): void {
    $this->withoutVite();

    $fixture = ($this->fixture)();
    $live = Project::factory()->create(['market_id' => $fixture['market']->id]);

    foreach ([$fixture['project'], $live] as $project) {
        CustomerProjectAccess::factory()->create([
            'user_id' => $fixture['customer']->id,
            'project_id' => $project->id,
        ]);
    }

    $fixture['project']->delete();

    $this->actingAs($fixture['admin'])
        ->get(route('admin.customers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Customers/Index')
            ->has('customers.0.customer_accesses', 1)
            ->where('customers.0.customer_accesses.0.project.id', $live->id));
});

it('revokes an access row even when its project is soft-deleted', function (): void {
    $fixture = ($this->fixture)();
    $access = CustomerProjectAccess::factory()->create([
        'user_id' => $fixture['customer']->id,
        'project_id' => $fixture['project']->id,
    ]);

    $fixture['project']->delete();

    $this->actingAs($fixture['admin'])
        ->delete(route('admin.customers.access.destroy', [$fixture['customer'], $fixture['project']]))
        ->assertRedirect()
        ->assertSessionHas('success', 'Customer access revoked.');

    expect(CustomerProjectAccess::find($access->id))->toBeNull();
});

it('denies the portal to non-customers', function (): void {
    $fixture = ($this->fixture)();
    $field = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->actingAs($field)
        ->get(route('portal.index'))
        ->assertForbidden();
});

it('allows an authorized customer to view the project details page', function (): void {
    $this->withoutVite();

    $fixture = ($this->fixture)();
    CustomerProjectAccess::factory()->create([
        'user_id' => $fixture['customer']->id,
        'project_id' => $fixture['project']->id,
    ]);

    $this->actingAs($fixture['customer'])
        ->get(route('projects.show', $fixture['project']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Show')
            ->where('project.id', $fixture['project']->id));
});

it('denies viewing the project details page to a customer without project access', function (): void {
    $fixture = ($this->fixture)();

    $this->actingAs($fixture['customer'])
        ->get(route('projects.show', $fixture['project']))
        ->assertForbidden();
});

it('exposes package site-note images and the manager avatar on the portal project page', function (): void {
    $this->withoutVite();

    $fixture = ($this->fixture)();
    $package = ($this->approvedPackage)($fixture);
    $package->addMedia(UploadedFile::fake()->image('note.jpg'))
        ->toMediaCollection(CloseoutPackage::PACKAGE_SITE_NOTES_COLLECTION);

    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->addMedia(UploadedFile::fake()->image('avatar.jpg'))
        ->toMediaCollection(User::PROFILE_PHOTO_COLLECTION);
    $fixture['project']->update(['manager_id' => $manager->id]);

    CustomerProjectAccess::factory()->create([
        'user_id' => $fixture['customer']->id,
        'project_id' => $fixture['project']->id,
    ]);

    $this->actingAs($fixture['customer'])
        ->get(route('portal.projects.show', $fixture['project']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customer/Portal/Projects/Show')
            ->has('project.packages.0.site_note_images', 1)
            ->where('project.manager.profile_photo_url', fn ($url) => is_string($url)
                && str_contains($url, 'profile-photo')));
});
