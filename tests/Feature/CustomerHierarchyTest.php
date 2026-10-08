<?php

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Customers\Actions\AssignEngineerToProjectAction;
use App\Domains\Customers\Actions\CreateCustomerEngineerAction;
use App\Domains\Customers\Actions\DeleteCustomerEngineerAction;
use App\Domains\Customers\Actions\UpdateCustomerEngineerAction;
use App\Domains\Customers\DTOs\CreateCustomerEngineerDTO;
use App\Domains\Customers\DTOs\UpdateCustomerEngineerDTO;
use App\Domains\Customers\Models\Customer;
use App\Domains\Customers\Models\CustomerEngineer;
use App\Domains\Customers\Queries\CustomerHierarchyQuery;
use App\Domains\Markets\Models\Market;
use App\Domains\PdfGeneration\Services\PdfRenderService;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Models\Template;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    // Engineer creation now provisions a portal User, whose UserCreated event
    // dispatches an SSO sync job — faked here, irrelevant to hierarchy tests.
    Queue::fake();
});

it('scopes engineers to a customer and a market (region)', function (): void {
    $customer = Customer::factory()->create(['name' => 'Verizon', 'code' => 'VZW']);
    $market = Market::factory()->create(['name' => 'Ohio']);

    $engineer = CustomerEngineer::factory()->create([
        'customer_id' => $customer->id,
        'market_id' => $market->id,
        'email' => 'jane.smith@verizon.com',
    ]);

    expect($engineer->customer->is($customer))->toBeTrue()
        ->and($engineer->market->is($market))->toBeTrue()
        ->and($engineer->is_default)->toBeFalse()
        ->and($customer->customerEngineers()->count())->toBe(1)
        ->and($market->customerEngineers()->count())->toBe(1);
});

it('rejects a duplicate engineer email within the same customer and region', function (): void {
    $customer = Customer::factory()->create();
    $market = Market::factory()->create();

    CustomerEngineer::factory()->create([
        'customer_id' => $customer->id,
        'market_id' => $market->id,
        'email' => 'duplicate@verizon.com',
    ]);

    expect(fn () => CustomerEngineer::factory()->create([
        'customer_id' => $customer->id,
        'market_id' => $market->id,
        'email' => 'duplicate@verizon.com',
    ]))->toThrow(QueryException::class);
});

it('allows the same engineer email across different regions', function (): void {
    $customer = Customer::factory()->create();

    CustomerEngineer::factory()->create([
        'customer_id' => $customer->id,
        'market_id' => Market::factory()->create()->id,
        'email' => 'shared@verizon.com',
    ]);

    $second = CustomerEngineer::factory()->create([
        'customer_id' => $customer->id,
        'market_id' => Market::factory()->create()->id,
        'email' => 'shared@verizon.com',
    ]);

    expect($second->exists)->toBeTrue();
});

it('links a project to its customer and customer engineer', function (): void {
    $customer = Customer::factory()->create();
    $market = Market::factory()->create();
    $engineer = CustomerEngineer::factory()->default()->create([
        'customer_id' => $customer->id,
        'market_id' => $market->id,
    ]);

    $project = Project::factory()->create([
        'market_id' => $market->id,
        'customer_id' => $customer->id,
        'customer_engineer_id' => $engineer->id,
    ]);

    expect($project->customer->is($customer))->toBeTrue()
        ->and($project->customerEngineer->is($engineer))->toBeTrue()
        ->and($customer->projects()->count())->toBe(1)
        ->and($engineer->projects()->count())->toBe(1);
});

it('nulls the project engineer link when the engineer is force deleted', function (): void {
    $engineer = CustomerEngineer::factory()->create();
    $project = Project::factory()->create([
        'customer_id' => $engineer->customer_id,
        'customer_engineer_id' => $engineer->id,
    ]);

    $engineer->forceDelete();

    expect($project->fresh()->customer_engineer_id)->toBeNull();
});

it('keeps a single default engineer per customer and region', function (): void {
    $customer = Customer::factory()->create();
    $market = Market::factory()->create();

    $first = app(CreateCustomerEngineerAction::class)->execute(new CreateCustomerEngineerDTO(
        customerId: $customer->id,
        marketId: $market->id,
        firstName: 'Jane',
        lastName: 'Smith',
        email: 'jane@verizon.com',
        password: 'portal-password',
        isDefault: true,
    ));

    $second = app(CreateCustomerEngineerAction::class)->execute(new CreateCustomerEngineerDTO(
        customerId: $customer->id,
        marketId: $market->id,
        firstName: 'Bob',
        lastName: 'Miller',
        email: 'bob@verizon.com',
        password: 'portal-password',
        isDefault: true,
    ));

    expect($first->refresh()->is_default)->toBeFalse()
        ->and($second->is_default)->toBeTrue();
});

it('automatically sets the first engineer created in a territory as default', function (): void {
    $customer = Customer::factory()->create();
    $market = Market::factory()->create();

    $first = app(CreateCustomerEngineerAction::class)->execute(new CreateCustomerEngineerDTO(
        customerId: $customer->id,
        marketId: $market->id,
        firstName: 'Jane',
        lastName: 'Smith',
        email: 'jane@verizon.com',
        password: 'portal-password',
        isDefault: false,
    ));

    expect($first->is_default)->toBeTrue();

    $second = app(CreateCustomerEngineerAction::class)->execute(new CreateCustomerEngineerDTO(
        customerId: $customer->id,
        marketId: $market->id,
        firstName: 'Bob',
        lastName: 'Miller',
        email: 'bob@verizon.com',
        password: 'portal-password',
        isDefault: false,
    ));

    expect($second->is_default)->toBeFalse()
        ->and($first->refresh()->is_default)->toBeTrue();
});

it('keeps default engineers independent across regions', function (): void {
    $customer = Customer::factory()->create();

    $marketA = Market::factory()->create();
    $marketB = Market::factory()->create();

    $engineerA = app(CreateCustomerEngineerAction::class)->execute(new CreateCustomerEngineerDTO(
        customerId: $customer->id,
        marketId: $marketA->id,
        firstName: 'Jane',
        lastName: 'Smith',
        email: 'jane@verizon.com',
        password: 'portal-password',
        isDefault: true,
    ));

    $engineerB = app(CreateCustomerEngineerAction::class)->execute(new CreateCustomerEngineerDTO(
        customerId: $customer->id,
        marketId: $marketB->id,
        firstName: 'Bob',
        lastName: 'Miller',
        email: 'bob@verizon.com',
        password: 'portal-password',
        isDefault: true,
    ));

    expect($engineerA->refresh()->is_default)->toBeTrue()
        ->and($engineerB->is_default)->toBeTrue();
});

it('promotes an engineer to default on update and demotes the previous one', function (): void {
    $customer = Customer::factory()->create();
    $market = Market::factory()->create();

    $current = CustomerEngineer::factory()->default()->create([
        'customer_id' => $customer->id,
        'market_id' => $market->id,
    ]);
    $challenger = CustomerEngineer::factory()->create([
        'customer_id' => $customer->id,
        'market_id' => $market->id,
    ]);

    $updated = app(UpdateCustomerEngineerAction::class)->execute(
        $challenger,
        new UpdateCustomerEngineerDTO(title: 'Construction PM', isDefault: true),
    );

    expect($updated->is_default)->toBeTrue()
        ->and($updated->title)->toBe('Construction PM')
        ->and($current->refresh()->is_default)->toBeFalse();
});

it('assigns a customer engineer to a project and syncs the legacy snapshot', function (): void {
    $engineer = CustomerEngineer::factory()->create([
        'name' => 'Jane Smith',
        'email' => 'jane@verizon.com',
        'phone' => '(555) 019-2834',
    ]);
    $project = Project::factory()->create(['market_id' => $engineer->market_id]);

    app(AssignEngineerToProjectAction::class)->execute($project, $engineer);

    expect($project->refresh())
        ->customer_engineer_id->toBe($engineer->id)
        ->customer_id->toBe($engineer->customer_id)
        ->vzw_engineer_name->toBe('Jane Smith')
        ->vzw_engineer_email->toBe('jane@verizon.com')
        ->vzw_engineer_phone->toBe('(555) 019-2834');
});

it('clears the project engineer link without wiping the legacy snapshot', function (): void {
    $engineer = CustomerEngineer::factory()->create(['name' => 'Jane Smith']);
    $project = Project::factory()->create(['market_id' => $engineer->market_id]);

    $action = app(AssignEngineerToProjectAction::class);
    $action->execute($project, $engineer);
    $action->execute($project, null);

    expect($project->refresh())
        ->customer_engineer_id->toBeNull()
        ->vzw_engineer_name->toBe('Jane Smith');
});

it('resolves the pair default engineer on projects without an assignment', function (): void {
    $customer = Customer::factory()->create();
    $market = Market::factory()->create();
    CustomerEngineer::factory()->default()->create([
        'customer_id' => $customer->id,
        'market_id' => $market->id,
        'name' => 'Jane Smith',
        'email' => 'jane@verizon.com',
        'phone' => '(555) 019-2834',
    ]);

    $project = Project::factory()->create([
        'customer_id' => $customer->id,
        'market_id' => $market->id,
        'customer_engineer_id' => null,
        'vzw_engineer_name' => null,
        'vzw_engineer_email' => null,
        'vzw_engineer_phone' => null,
    ]);

    expect($project->vzw_engineer_name)->toBe('Jane Smith')
        ->and($project->vzw_engineer_email)->toBe('jane@verizon.com')
        ->and($project->vzw_engineer_phone)->toBe('(555) 019-2834');
});

it('returns empty engineer fields when no pair engineer exists', function (): void {
    $project = Project::factory()->create([
        'customer_engineer_id' => null,
        'vzw_engineer_name' => null,
        'vzw_engineer_email' => null,
        'vzw_engineer_phone' => null,
    ]);

    expect($project->vzw_engineer_name)->toBeNull()
        ->and($project->vzw_engineer_email)->toBeNull()
        ->and($project->vzw_engineer_phone)->toBeNull();
});

it('creates the engineer together with a linked customer portal account', function (): void {
    $customer = Customer::factory()->create();
    $market = Market::factory()->create();
    $usersBefore = User::count();

    $engineer = app(CreateCustomerEngineerAction::class)->execute(new CreateCustomerEngineerDTO(
        customerId: $customer->id,
        marketId: $market->id,
        firstName: 'Jane',
        lastName: 'Smith',
        email: 'jane@verizon.com',
        password: 'portal-password',
    ));

    $user = $engineer->user;

    expect($engineer->name)->toBe('Jane Smith')
        ->and($user)->not->toBeNull()
        ->and($user->hasRole(Role::CUSTOMER->value))->toBeTrue()
        ->and(User::count())->toBe($usersBefore + 1)
        ->and(Hash::check('portal-password', (string) $user->password))->toBeTrue();
});

it('removes the portal account when the engineer is deleted', function (): void {
    $customer = Customer::factory()->create();
    $market = Market::factory()->create();

    $engineer = app(CreateCustomerEngineerAction::class)->execute(new CreateCustomerEngineerDTO(
        customerId: $customer->id,
        marketId: $market->id,
        firstName: 'Jane',
        lastName: 'Smith',
        email: 'jane@verizon.com',
        password: 'portal-password',
    ));

    app(DeleteCustomerEngineerAction::class)->execute($engineer);

    expect($engineer->fresh()->trashed())->toBeTrue()
        ->and(User::withTrashed()->find($engineer->user_id)?->trashed())->toBeTrue();
});

it('lets the same email be reused after the engineer was removed', function (): void {
    $customer = Customer::factory()->create();
    $market = Market::factory()->create();

    $engineer = app(CreateCustomerEngineerAction::class)->execute(new CreateCustomerEngineerDTO(
        customerId: $customer->id,
        marketId: $market->id,
        firstName: 'Jane',
        lastName: 'Smith',
        email: 'jane@verizon.com',
        password: 'portal-password',
    ));
    $oldUserId = $engineer->user_id;

    app(DeleteCustomerEngineerAction::class)->execute($engineer);

    $readded = app(CreateCustomerEngineerAction::class)->execute(new CreateCustomerEngineerDTO(
        customerId: $customer->id,
        marketId: $market->id,
        firstName: 'Jane',
        lastName: 'Smith',
        email: 'jane@verizon.com',
        password: 'portal-password',
    ));

    expect($readded->user_id)->not->toBe($oldUserId)
        ->and($readded->user->hasRole(Role::CUSTOMER->value))->toBeTrue()
        ->and(User::where('email', 'jane@verizon.com')->count())->toBe(1);
});

it('lists selectable engineers default-first and resolves the default', function (): void {
    $customer = Customer::factory()->create();
    $market = Market::factory()->create();

    CustomerEngineer::factory()->default()->create([
        'customer_id' => $customer->id,
        'market_id' => $market->id,
        'name' => 'Zed Default',
    ]);
    CustomerEngineer::factory()->create([
        'customer_id' => $customer->id,
        'market_id' => $market->id,
        'name' => 'Amy Other',
    ]);

    $query = app(CustomerHierarchyQuery::class);

    expect($query->defaultEngineerFor($customer->id, $market->id)?->name)->toBe('Zed Default')
        ->and($query->engineersFor($customer->id, $market->id)->pluck('name')->all())
        ->toBe(['Zed Default', 'Amy Other']);
});

it('lets an admin create a customer and normalises its code', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    $this->actingAs($admin)
        ->post(route('admin.settings.customers.store'), [
            'name' => 'Verizon',
            'code' => 'vzw',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Customer::query()->where('code', 'VZW')->exists())->toBeTrue();
});

it('requires a unique customer code', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    Customer::factory()->create(['code' => 'VZW']);

    $this->actingAs($admin)
        ->post(route('admin.settings.customers.store'), [
            'name' => 'Verizon Two',
            'code' => 'vzw',
        ])
        ->assertSessionHasErrors('code');
});

it('allows creating a customer with a code that was previously deleted without constraint violation', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $oldCustomer = Customer::factory()->create(['code' => 'ATT', 'name' => 'Old ATT']);

    $this->actingAs($admin)
        ->delete(route('admin.settings.customers.destroy', $oldCustomer))
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->post(route('admin.settings.customers.store'), [
            'name' => 'New AT&T',
            'code' => 'ATT',
        ])
        ->assertSessionHasNoErrors();

    expect(Customer::query()->where('code', 'ATT')->value('name'))->toBe('New AT&T');
});

it('lets an admin add a default engineer to a region', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $customer = Customer::factory()->create();
    $market = Market::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.settings.customer-engineers.store'), [
            'customer_id' => $customer->id,
            'market_id' => $market->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@verizon.com',
            'password' => 'portal-password',
            'password_confirmation' => 'portal-password',
            'is_default' => true,
        ])
        ->assertSessionHasNoErrors();

    expect(CustomerEngineer::query()
        ->where('email', 'jane@verizon.com')
        ->where('is_default', true)
        ->whereNotNull('user_id')
        ->exists())->toBeTrue()
        ->and(User::query()->where('email', 'jane@verizon.com')->first()?->hasRole(Role::CUSTOMER->value))->toBeTrue();
});

it('stores an optional profile photo on the linked portal account when adding an engineer', function (): void {
    Storage::fake('private');
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $customer = Customer::factory()->create();
    $market = Market::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.settings.customer-engineers.store'), [
            'customer_id' => $customer->id,
            'market_id' => $market->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@verizon.com',
            'password' => 'portal-password',
            'password_confirmation' => 'portal-password',
            'profile_photo' => UploadedFile::fake()->image('avatar.jpg'),
        ])
        ->assertSessionHasNoErrors();

    $engineer = CustomerEngineer::query()->where('email', 'jane@verizon.com')->firstOrFail();
    $user = $engineer->user()->firstOrFail();

    expect($user->getFirstMedia(User::PROFILE_PHOTO_COLLECTION))->not->toBeNull()
        ->and($user->profilePhotoUrl())->not->toBeNull();
});

it('rejects an invalid profile photo when adding an engineer', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $customer = Customer::factory()->create();
    $market = Market::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.settings.customer-engineers.store'), [
            'customer_id' => $customer->id,
            'market_id' => $market->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@verizon.com',
            'password' => 'portal-password',
            'password_confirmation' => 'portal-password',
            'profile_photo' => UploadedFile::fake()->create('huge.pdf', 3000, 'application/pdf'),
        ])
        ->assertSessionHasErrors('profile_photo');

    expect(CustomerEngineer::query()->count())->toBe(0);
});

it('replaces the profile photo on the linked portal account when editing an engineer', function (): void {
    Storage::fake('private');
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $customer = Customer::factory()->create();
    $market = Market::factory()->create();

    $engineer = app(CreateCustomerEngineerAction::class)->execute(new CreateCustomerEngineerDTO(
        customerId: $customer->id,
        marketId: $market->id,
        firstName: 'Jane',
        lastName: 'Smith',
        email: 'jane@verizon.com',
        password: 'portal-password',
    ));
    $user = $engineer->user()->firstOrFail();
    $user->addMedia(UploadedFile::fake()->image('old.jpg'))->toMediaCollection(User::PROFILE_PHOTO_COLLECTION);
    $oldMediaId = $user->getFirstMedia(User::PROFILE_PHOTO_COLLECTION)?->id;

    // Matches the frontend: POST + _method=put so PHP fills $_POST/$_FILES.
    $this->actingAs($admin)
        ->post(route('admin.settings.customer-engineers.update', $engineer), [
            '_method' => 'put',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@verizon.com',
            'profile_photo' => UploadedFile::fake()->image('new.jpg'),
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->getFirstMedia(User::PROFILE_PHOTO_COLLECTION)?->id)->not->toBe($oldMediaId)
        ->and($user->profilePhotoUrl())->not->toBeNull();
});

it('exposes the engineer profile photo URL in the App Settings hierarchy payload', function (): void {
    Storage::fake('private');
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $customer = Customer::factory()->create();
    $market = Market::factory()->create();

    $engineer = app(CreateCustomerEngineerAction::class)->execute(new CreateCustomerEngineerDTO(
        customerId: $customer->id,
        marketId: $market->id,
        firstName: 'Jane',
        lastName: 'Smith',
        email: 'jane@verizon.com',
        password: 'portal-password',
        profilePhoto: UploadedFile::fake()->image('avatar.jpg'),
    ));

    $this->withoutVite()
        ->actingAs($admin)
        ->get(route('admin.settings.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('customer_hierarchy.customers.0.engineers.0.profile_photo_url')
                ->where(
                    'customer_hierarchy.customers.0.engineers.0.profile_photo_url',
                    $engineer->user()->firstOrFail()->profilePhotoUrl()
                )
        );
});

it('requires a region when adding an engineer', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $customer = Customer::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.settings.customer-engineers.store'), [
            'customer_id' => $customer->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@verizon.com',
            'password' => 'portal-password',
            'password_confirmation' => 'portal-password',
        ])
        ->assertSessionHasErrors('market_id');
});

it('rejects a duplicate engineer email in the same customer and region', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $customer = Customer::factory()->create();
    $market = Market::factory()->create();

    CustomerEngineer::factory()->create([
        'customer_id' => $customer->id,
        'market_id' => $market->id,
        'email' => 'duplicate@verizon.com',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.settings.customer-engineers.store'), [
            'customer_id' => $customer->id,
            'market_id' => $market->id,
            'first_name' => 'Someone',
            'last_name' => 'Else',
            'email' => 'duplicate@verizon.com',
            'password' => 'portal-password',
            'password_confirmation' => 'portal-password',
        ])
        ->assertSessionHasErrors('email');
});

it('rejects an engineer email that already belongs to a user account', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    User::factory()->create(['email' => 'taken@verizon.com']);
    $customer = Customer::factory()->create();
    $market = Market::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.settings.customer-engineers.store'), [
            'customer_id' => $customer->id,
            'market_id' => $market->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'taken@verizon.com',
            'password' => 'portal-password',
            'password_confirmation' => 'portal-password',
        ])
        ->assertSessionHasErrors('email');
});

it('lets a manager add an engineer inside a region they manage', function (): void {
    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    $customer = Customer::factory()->create();

    $this->actingAs($manager)
        ->post(route('admin.settings.customer-engineers.store'), [
            'customer_id' => $customer->id,
            'market_id' => $market->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@verizon.com',
            'password' => 'portal-password',
            'password_confirmation' => 'portal-password',
        ])
        ->assertSessionHasNoErrors();

    expect(CustomerEngineer::query()->where('email', 'jane@verizon.com')->exists())->toBeTrue();
});

it('blocks a manager from adding an engineer outside their regions', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $customer = Customer::factory()->create();
    $foreignMarket = Market::factory()->create();

    $this->actingAs($manager)
        ->post(route('admin.settings.customer-engineers.store'), [
            'customer_id' => $customer->id,
            'market_id' => $foreignMarket->id,
            'name' => 'Jane Smith',
            'email' => 'jane@verizon.com',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(CustomerEngineer::query()->count())->toBe(0);
});

it('blocks a manager from editing an engineer outside their regions', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $engineer = CustomerEngineer::factory()->create(['name' => 'Original Name']);

    $this->actingAs($manager)
        ->put(route('admin.settings.customer-engineers.update', $engineer), ['name' => 'Changed'])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($engineer->fresh()->name)->toBe('Original Name');
});

it('prevents deleting a customer that still has projects', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $customer = Customer::factory()->create();
    Project::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($admin)
        ->delete(route('admin.settings.customers.destroy', $customer))
        ->assertRedirect()
        ->assertSessionHas('error', 'This customer still has 1 project. Reassign or remove them in the Projects module before deleting this customer.');

    expect(Customer::query()->find($customer->id))->not->toBeNull();
});

it('blocks deleting a customer whose project is not assigned to any engineer', function (): void {
    // Real-world case: an engineer reads "0 projects" because the customer's
    // project has no customer_engineer_id, yet the customer cannot be deleted.
    // The delete guard counts the customer's own projects, not the engineer's.
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $market = Market::factory()->create();
    $customer = Customer::factory()->create(['name' => 'AT&T']);
    $engineer = CustomerEngineer::factory()->create([
        'customer_id' => $customer->id,
        'market_id' => $market->id,
        'name' => 'Silly Marker',
    ]);
    Project::factory()->create(['customer_id' => $customer->id, 'customer_engineer_id' => null]);

    $this->withoutVite()
        ->actingAs($admin)
        ->get(route('admin.settings.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('customer_hierarchy.customers.0.engineers.0.assigned_projects', [])
                ->where('customer_hierarchy.customers.0.projects_count', 1)
        );

    $this->actingAs($admin)
        ->delete(route('admin.settings.customers.destroy', $customer))
        ->assertRedirect()
        ->assertSessionHas('error', 'This customer still has 1 project. Reassign or remove them in the Projects module before deleting this customer.');

    expect($engineer->projects()->count())->toBe(0)
        ->and(Customer::query()->find($customer->id))->not->toBeNull();
});

it('soft deletes an engineer so historical snapshots survive', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $engineer = CustomerEngineer::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.settings.customer-engineers.destroy', $engineer))
        ->assertSessionHasNoErrors();

    expect(CustomerEngineer::query()->find($engineer->id))->toBeNull()
        ->and(CustomerEngineer::withTrashed()->find($engineer->id))->not->toBeNull();
});

it('exposes the customer hierarchy to the App Settings console', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $customer = Customer::factory()->create(['name' => 'Verizon', 'code' => 'VZW']);
    $market = Market::factory()->create(['name' => 'Ohio']);
    CustomerEngineer::factory()->default()->create([
        'customer_id' => $customer->id,
        'market_id' => $market->id,
        'name' => 'Jane Smith',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.settings.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Admin/Settings/AppSettings')
                ->where('customer_hierarchy.customers.0.name', 'Verizon')
                ->where('customer_hierarchy.customers.0.engineers.0.name', 'Jane Smith')
                ->where('customer_hierarchy.customers.0.engineers.0.is_default', true)
                ->where('customer_hierarchy.metrics.customers', 1)
                ->where('customer_hierarchy.metrics.active_engineers', 1)
        );
});

it('assigns a customer engineer when creating a project', function (): void {
    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);

    $customer = Customer::factory()->create();
    $engineer = CustomerEngineer::factory()->create([
        'customer_id' => $customer->id,
        'market_id' => $market->id,
        'name' => 'Jane Smith',
        'email' => 'jane@verizon.com',
    ]);

    $this->actingAs($manager)
        ->post(route('projects.store'), [
            'market_id' => $market->id,
            'customer_id' => $customer->id,
            'customer_engineer_id' => $engineer->id,
            'name' => 'Tower 456',
            'address' => '123 Main St',
            'completed_date' => '2026-12-01',
            'awarded_date' => '2026-06-01',
            'rec_issued_date' => '2026-06-05',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $project = Project::query()->where('name', 'Tower 456')->firstOrFail();

    expect($project->customer_engineer_id)->toBe($engineer->id)
        ->and($project->customer_id)->toBe($customer->id)
        ->and($project->vzw_engineer_name)->toBe('Jane Smith')
        ->and($project->vzw_engineer_email)->toBe('jane@verizon.com');
});

it('rejects an engineer that does not belong to the chosen customer and region', function (): void {
    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);

    $customer = Customer::factory()->create();
    $foreignEngineer = CustomerEngineer::factory()->create([
        'customer_id' => $customer->id,
        'market_id' => Market::factory()->create()->id,
    ]);

    $this->actingAs($manager)
        ->post(route('projects.store'), [
            'market_id' => $market->id,
            'customer_id' => $customer->id,
            'customer_engineer_id' => $foreignEngineer->id,
            'name' => 'Bad Project',
            'completed_date' => '2026-09-01',
        ])
        ->assertSessionHasErrors('customer_engineer_id');

    expect(Project::query()->where('name', 'Bad Project')->exists())->toBeFalse();
});

it('reassigns the project engineer on update and keeps the snapshot when cleared', function (): void {
    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    $customer = Customer::factory()->create();

    $first = CustomerEngineer::factory()->create([
        'customer_id' => $customer->id,
        'market_id' => $market->id,
        'email' => 'first@verizon.com',
    ]);
    $second = CustomerEngineer::factory()->create([
        'customer_id' => $customer->id,
        'market_id' => $market->id,
        'email' => 'second@verizon.com',
    ]);

    $project = Project::factory()->create([
        'market_id' => $market->id,
        'manager_id' => $manager->id,
        'customer_id' => $customer->id,
        'customer_engineer_id' => $first->id,
    ]);

    $this->actingAs($manager)
        ->put(route('projects.update', $project), [
            'name' => $project->name,
            'customer_id' => $customer->id,
            'customer_engineer_id' => $second->id,
            'completed_date' => '2026-09-01',
        ])
        ->assertSessionHasNoErrors();

    expect($project->fresh()->customer_engineer_id)->toBe($second->id)
        ->and($project->fresh()->vzw_engineer_email)->toBe('second@verizon.com');

    $this->actingAs($manager)
        ->put(route('projects.update', $project), [
            'name' => $project->name,
            'customer_engineer_id' => '',
            'completed_date' => '2026-09-01',
        ])
        ->assertSessionHasNoErrors();

    expect($project->fresh()->customer_engineer_id)->toBeNull()
        ->and($project->fresh()->vzw_engineer_email)->toBe('second@verizon.com');
});

it('unlinks the customer when the customer field is submitted blank on update', function (): void {
    // Regression: choosing "Select a customer" on the edit page must actually
    // detach the project. The form always posts the field, so a blank value is
    // an explicit unlink, not "leave unchanged".
    $market = Market::factory()->create();
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($market->id);
    $customer = Customer::factory()->create();

    $project = Project::factory()->create([
        'market_id' => $market->id,
        'manager_id' => $manager->id,
        'customer_id' => $customer->id,
    ]);

    $this->actingAs($manager)
        ->put(route('projects.update', $project), [
            'name' => $project->name,
            'customer_id' => '',
            'customer_engineer_id' => '',
            'completed_date' => '2026-09-01',
        ])
        ->assertSessionHasNoErrors();

    expect($project->fresh()->customer_id)->toBeNull()
        ->and($project->fresh()->customer_engineer_id)->toBeNull();
});

it('brands the PDF cover engineer box with the customer name', function (): void {
    $customer = Customer::factory()->create(['name' => 'AT&T']);
    $market = Market::factory()->create();
    $template = Template::factory()->create(['market_id' => $market->id]);
    $project = Project::factory()->create([
        'market_id' => $market->id,
        'customer_id' => $customer->id,
    ]);
    $package = CloseoutPackage::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
    ]);

    $result = app(PdfRenderService::class)->render($package, [], 'R1.0', '2026-09-01');

    expect($result['html'])
        ->toContain('AT&amp;T ENGINEER:')
        ->not->toContain('VZW ENGINEER:');
});

it('syncs engineer edits onto the portal account including password and active state', function (): void {
    // Email and password sync synchronously, bypassing Queue::fake().
    config()->set('services.authcenter.url', 'https://sso.test');
    config()->set('services.sync.secret', 'test-sync-secret');
    Http::fake([
        'https://sso.test/api/internal-sync-user' => Http::response(['message' => 'Synced'], 200),
    ]);

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $customer = Customer::factory()->create();
    $market = Market::factory()->create();

    $engineer = app(CreateCustomerEngineerAction::class)->execute(new CreateCustomerEngineerDTO(
        customerId: $customer->id,
        marketId: $market->id,
        firstName: 'Jane',
        lastName: 'Smith',
        email: 'jane@verizon.com',
        password: 'portal-password',
    ));

    $this->actingAs($admin)
        ->put(route('admin.settings.customer-engineers.update', $engineer), [
            'first_name' => 'Janet',
            'last_name' => 'Smith',
            'email' => 'janet@verizon.com',
            'password' => 'new-portal-password',
            'password_confirmation' => 'new-portal-password',
            'is_active' => false,
        ])
        ->assertSessionHasNoErrors();

    $engineer->refresh();
    $user = $engineer->user()->firstOrFail();

    expect($engineer->name)->toBe('Janet Smith')
        ->and($user->name)->toBe('Janet Smith')
        ->and($user->email)->toBe('janet@verizon.com')
        ->and($user->is_active)->toBeFalse()
        ->and(Hash::check('new-portal-password', (string) $user->password))->toBeTrue();
});

it('shows an assigned engineer their projects in the portal without a grant row', function (): void {
    $customer = Customer::factory()->create();
    $market = Market::factory()->create();

    $engineer = app(CreateCustomerEngineerAction::class)->execute(new CreateCustomerEngineerDTO(
        customerId: $customer->id,
        marketId: $market->id,
        firstName: 'Jane',
        lastName: 'Smith',
        email: 'jane@verizon.com',
        password: 'portal-password',
    ));

    $assigned = Project::factory()->create([
        'customer_id' => $customer->id,
        'market_id' => $market->id,
        'customer_engineer_id' => $engineer->id,
    ]);
    Project::factory()->create(['customer_id' => $customer->id]);

    $this->withoutVite()
        ->actingAs($engineer->user()->firstOrFail())
        ->get(route('portal.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('projects', 1)
                ->where('projects.0.id', $assigned->id)
        );
});

it('shows a manager only the engineers inside the regions assigned to them', function (): void {
    $this->withoutVite();

    $theirMarket = Market::factory()->create(['name' => 'Their Region']);
    $otherMarket = Market::factory()->create(['name' => 'Other Region']);

    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);
    $manager->markets()->attach($theirMarket->id);

    $customer = Customer::factory()->create(['name' => 'Verizon']);

    $theirs = CustomerEngineer::factory()->create([
        'customer_id' => $customer->id,
        'market_id' => $theirMarket->id,
        'name' => 'Their Engineer',
    ]);
    $foreign = CustomerEngineer::factory()->create([
        'customer_id' => $customer->id,
        'market_id' => $otherMarket->id,
        'name' => 'Foreign Engineer',
    ]);

    $this->actingAs($manager)
        ->get(route('admin.settings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            // Company names stay visible — the manager needs them as the
            // parent picker when adding an engineer.
            ->where('customer_hierarchy.customers.0.name', 'Verizon')
            ->has('customer_hierarchy.customers.0.engineers', 1)
            ->where('customer_hierarchy.customers.0.engineers.0.id', $theirs->id)
            // Region picker offers their region, not someone else's.
            ->has('customer_hierarchy.regions', 1)
            ->where('customer_hierarchy.regions.0.name', 'Their Region')
            ->where('customer_hierarchy.metrics.active_engineers', 1));

    // The foreign engineer is simply not in the payload.
    $this->assertStringNotContainsString(
        'Foreign Engineer',
        (string) json_encode($this->actingAs($manager)->get(route('admin.settings.index'))->viewData('page')['props']),
    );
});

it('shows an admin every region and every engineer', function (): void {
    $this->withoutVite();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $marketA = Market::factory()->create(['name' => 'Region A']);
    $marketB = Market::factory()->create(['name' => 'Region B']);
    $customer = Customer::factory()->create(['name' => 'Verizon']);

    CustomerEngineer::factory()->create(['customer_id' => $customer->id, 'market_id' => $marketA->id]);
    CustomerEngineer::factory()->create(['customer_id' => $customer->id, 'market_id' => $marketB->id]);

    $this->actingAs($admin)
        ->get(route('admin.settings.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('customer_hierarchy.customers.0.engineers', 2)
            ->has('customer_hierarchy.regions', 2)
            ->where('customer_hierarchy.metrics.active_engineers', 2)
            // Admins still get the full console.
            ->has('media_quotas')
            ->has('mail_settings'));
});
