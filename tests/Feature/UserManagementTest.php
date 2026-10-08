<?php

use App\Domains\Users\Events\UserUpdated;
use App\Domains\Users\Jobs\SyncUserToSsoJob;
use App\Domains\Users\Models\User;
use App\Domains\Users\Services\SsoUserSyncService;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->withoutVite();

    config()->set('services.authcenter.url', 'https://sso.test');
    config()->set('services.authcenter.user_origin', 'solucomp_cop');
    config()->set('services.sync.secret', 'test-sync-secret');
});

it('lists users for admins', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    User::factory()->create(['name' => 'John Field'])->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk();
});

it('creates a user with company, phone and a profile photo', function (): void {
    Storage::fake('private');
    Queue::fake();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'first_name' => 'John',
            'last_name' => 'Field',
            'email' => 'john@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'role' => Role::FIELD_SUPERVISOR->value,
            'company' => 'Verizon Wireless',
            'phone' => '(123) 456-7890',
            'profile_photo' => UploadedFile::fake()->image('avatar.jpg'),
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success');

    $user = User::where('email', 'john@example.com')->firstOrFail();

    expect($user->company)->toBe('Verizon Wireless')
        ->and($user->phone)->toBe('(123) 456-7890')
        ->and($user->getFirstMedia(User::PROFILE_PHOTO_COLLECTION))->not->toBeNull();

    Queue::assertPushed(SyncUserToSsoJob::class);
});

it('accepts missing optional company, phone and profile photo', function (): void {
    Queue::fake();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'first_name' => 'John',
            'last_name' => 'Field',
            'email' => 'john@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'role' => Role::FIELD_SUPERVISOR->value,
        ])
        ->assertRedirect(route('admin.users.index'));

    $user = User::where('email', 'john@example.com')->firstOrFail();

    expect($user->company)->toBeNull()
        ->and($user->phone)->toBeNull()
        ->and($user->getFirstMedia(User::PROFILE_PHOTO_COLLECTION))->toBeNull();
});

it('validates company length, phone format and profile photo size for users', function (): void {
    Queue::fake();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'first_name' => 'John',
            'last_name' => 'Field',
            'email' => 'john@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'role' => Role::FIELD_SUPERVISOR->value,
            'company' => str_repeat('a', 51),
            'phone' => 'not-a-phone',
            'profile_photo' => UploadedFile::fake()->create('huge.pdf', 3000, 'application/pdf'),
        ])
        ->assertSessionHasErrors(['company', 'phone', 'profile_photo']);

    Queue::assertNothingPushed();
});

it('updates company, phone and replaces the profile photo', function (): void {
    Storage::fake('private');
    Queue::fake();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $user = User::factory()->create([
        'name' => 'John Field',
        'email' => 'john@example.com',
        'company' => 'Old Co',
        'phone' => '(111) 222-3333',
    ])->assignRole(Role::FIELD_SUPERVISOR->value);

    $user->addMedia(UploadedFile::fake()->image('old.jpg'))->toMediaCollection(User::PROFILE_PHOTO_COLLECTION);
    $old = $user->getFirstMedia(User::PROFILE_PHOTO_COLLECTION);

    // Mirrors the frontend, which sends _method=put via a POST so PHP can parse
    // the multipart body (a true PUT would lose every field when a file is present).
    $this->actingAs($admin)
        ->post(route('admin.users.update', $user), [
            '_method' => 'put',
            'first_name' => 'John',
            'last_name' => 'Field',
            'email' => 'john@example.com',
            'role' => Role::FIELD_SUPERVISOR->value,
            'company' => 'New Co',
            'phone' => '(444) 555-6666',
            'profile_photo' => UploadedFile::fake()->image('new.jpg'),
        ])
        ->assertRedirect(route('admin.users.index'));

    $user->refresh();

    expect($user->company)->toBe('New Co')
        ->and($user->phone)->toBe('(444) 555-6666')
        ->and($user->getFirstMedia(User::PROFILE_PHOTO_COLLECTION)?->id)->not->toBe($old->id);

    Queue::assertPushed(SyncUserToSsoJob::class);
});

it('clears company and phone when emptied on update', function (): void {
    Queue::fake();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $user = User::factory()->create([
        'name' => 'John Field',
        'email' => 'john@example.com',
        'company' => 'Old Co',
        'phone' => '(111) 222-3333',
    ])->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->actingAs($admin)
        ->post(route('admin.users.update', $user), [
            '_method' => 'put',
            'first_name' => 'John',
            'last_name' => 'Field',
            'email' => 'john@example.com',
            'role' => Role::FIELD_SUPERVISOR->value,
            'company' => '',
            'phone' => '',
        ])
        ->assertRedirect(route('admin.users.index'));

    $user->refresh();

    expect($user->company)->toBeNull()
        ->and($user->phone)->toBeNull();
});

it('serves the profile photo and exposes the cache-busted url on the list and edit pages', function (): void {
    Storage::fake('private');

    $admin = User::factory()->create(['name' => 'Zoe Admin'])->assignRole(Role::ADMIN->value);
    $user = User::factory()->create(['name' => 'John Field'])->assignRole(Role::FIELD_SUPERVISOR->value);
    $user->addMedia(UploadedFile::fake()->image('avatar.jpg'))->toMediaCollection(User::PROFILE_PHOTO_COLLECTION);

    $media = $user->getFirstMedia(User::PROFILE_PHOTO_COLLECTION);
    $expectedUrl = route('users.profile-photo.show', $user).'?v='.$media->id;

    $this->actingAs($admin)
        ->get(route('users.profile-photo.show', $user))
        ->assertOk()
        ->assertHeader('content-type', 'image/jpeg');

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users', 2)
            ->where('users.0.profile_photo_url', $expectedUrl));

    $this->actingAs($admin)
        ->get(route('admin.users.edit', $user))
        ->assertInertia(fn (Assert $page) => $page
            ->where('user.profile_photo_url', $expectedUrl));
});

it('returns 404 when no profile photo exists', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $user = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->actingAs($admin)
        ->get(route('users.profile-photo.show', $user))
        ->assertNotFound();
});

it('lets an authenticated user view their own and another user\'s profile photo', function (): void {
    Storage::fake('private');

    $field = User::factory()->create(['name' => 'Jane Field'])->assignRole(Role::FIELD_SUPERVISOR->value);
    $other = User::factory()->create(['name' => 'John Field'])->assignRole(Role::FIELD_SUPERVISOR->value);

    $field->addMedia(UploadedFile::fake()->image('mine.jpg'))->toMediaCollection(User::PROFILE_PHOTO_COLLECTION);
    $other->addMedia(UploadedFile::fake()->image('theirs.jpg'))->toMediaCollection(User::PROFILE_PHOTO_COLLECTION);

    $this->actingAs($field)
        ->get(route('users.profile-photo.show', $field))
        ->assertOk();

    $this->actingAs($field)
        ->get(route('users.profile-photo.show', $other))
        ->assertOk();

    $this->post(route('logout'));

    $this->get(route('users.profile-photo.show', $field))
        ->assertRedirect(route('session.expired'));
});

it('denies user management to non-admins', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);

    $this->actingAs($manager)
        ->get(route('admin.users.index'))
        ->assertForbidden();

    Queue::fake();

    $this->actingAs($manager)
        ->post(route('admin.users.store'), [
            'first_name' => 'John',
            'last_name' => 'Field',
            'email' => 'john@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'role' => Role::FIELD_SUPERVISOR->value,
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');

    Queue::assertNothingPushed();
});

it('creates a user and syncs it to the SSO', function (): void {
    Queue::fake();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'first_name' => 'John',
            'last_name' => 'Field',
            'email' => 'john@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'role' => Role::FIELD_SUPERVISOR->value,
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success');

    $user = User::where('email', 'john@example.com')->firstOrFail();

    expect($user->name)->toBe('John Field')
        ->and($user->uuid)->not->toBeNull()
        ->and($user->is_active)->toBeTrue()
        ->and($user->hasRole(Role::FIELD_SUPERVISOR->value))->toBeTrue();

    Queue::assertPushed(SyncUserToSsoJob::class, fn (SyncUserToSsoJob $job): bool => $job->payload['action'] === 'created'
        && $job->payload['uuid'] === $user->uuid
        && $job->payload['personal_email'] === 'john@example.com'
        && $job->payload['full_name'] === 'John Field'
        && $job->payload['last_name'] === 'Field'
        && $job->payload['role'] === Role::FIELD_SUPERVISOR->value
        && $job->payload['user_origin'] === 'solucomp_cop'
        && password_verify('secret-password', $job->payload['password']));
});

it('requires a unique email and a valid role when creating a user', function (): void {
    Queue::fake();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    User::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'first_name' => 'John',
            'last_name' => 'Field',
            'email' => 'taken@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'role' => 'superuser',
        ])
        ->assertSessionHasErrors(['email', 'role']);

    Queue::assertNothingPushed();
});

it('updates a user and syncs the update to the SSO', function (): void {
    Queue::fake();

    Http::fake([
        'https://sso.test/api/internal-sync-user' => Http::response(['message' => 'Synced'], 200),
    ]);

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $user = User::factory()->create(['name' => 'John Field', 'email' => 'john@example.com'])
        ->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $user), [
            'first_name' => 'Johnny',
            'last_name' => 'Fields',
            'email' => 'johnny@example.com',
            'role' => Role::MANAGER->value,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.users.index'));

    $user->refresh();

    expect($user->name)->toBe('Johnny Fields')
        ->and($user->email)->toBe('johnny@example.com')
        ->and($user->hasRole(Role::MANAGER->value))->toBeTrue();

    // The email change is synced synchronously, so no async job is queued.
    Http::assertSent(fn (Request $request): bool => $request['action'] === 'updated'
        && $request['personal_email'] === 'johnny@example.com'
        && $request['full_name'] === 'Johnny Fields');

    Queue::assertNotPushed(SyncUserToSsoJob::class);
});

it('keeps the email valid when updating without changing it', function (): void {
    Queue::fake();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $user = User::factory()->create(['email' => 'same@example.com'])->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->actingAs($admin)
        ->post(route('admin.users.update', $user), [
            '_method' => 'put',
            'first_name' => 'John',
            'last_name' => 'Field',
            'email' => 'same@example.com',
            'role' => Role::FIELD_SUPERVISOR->value,
        ])
        ->assertRedirect(route('admin.users.index'));
});

it('changes a user password and syncs the new hash to the SSO', function (): void {
    Queue::fake();

    Http::fake([
        'https://sso.test/api/internal-sync-user' => Http::response(['message' => 'Synced'], 200),
    ]);

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $user = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->actingAs($admin)
        ->post(route('admin.users.change-password', $user), [
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
        ])
        ->assertRedirect(route('admin.users.index'));

    $user->refresh();

    expect(password_verify('new-secret-password', $user->password))->toBeTrue();

    // The password change is synced synchronously, so no async job is queued.
    Http::assertSent(fn (Request $request): bool => $request['action'] === 'updated'
        && password_verify('new-secret-password', $request['password']));

    Queue::assertNotPushed(SyncUserToSsoJob::class);
});

it('toggles a user active status and syncs it to the SSO', function (): void {
    Queue::fake();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $user = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->actingAs($admin)
        ->post(route('admin.users.toggle-active', $user))
        ->assertRedirect(route('admin.users.index'));

    $user->refresh();

    expect($user->is_active)->toBeFalse();

    Queue::assertPushed(SyncUserToSsoJob::class, fn (SyncUserToSsoJob $job): bool => $job->payload['action'] === 'deactivated');

    $this->actingAs($admin)
        ->post(route('admin.users.toggle-active', $user))
        ->assertRedirect(route('admin.users.index'));

    $user->refresh();

    expect($user->is_active)->toBeTrue();

    Queue::assertPushed(SyncUserToSsoJob::class, fn (SyncUserToSsoJob $job): bool => $job->payload['action'] === 'activated');
});

it('deletes a user locally without syncing to the SSO', function (): void {
    Queue::fake();

    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $user = User::factory()->create()->assignRole(Role::FIELD_SUPERVISOR->value);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $user))
        ->assertRedirect(route('admin.users.index'));

    $this->assertSoftDeleted('users', ['id' => $user->id]);

    Queue::assertNothingPushed();
});

it('prevents an admin from deleting or changing their own account', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $admin))
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');

    $this->actingAs($admin)
        ->post(route('admin.users.change-password', $admin), [
            'password' => 'new-secret-password',
            'password_confirmation' => 'new-secret-password',
        ])
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');

    $this->actingAs($admin)
        ->post(route('admin.users.toggle-active', $admin))
        ->assertRedirect()
        ->assertSessionHas('error', 'You do not have permission to perform this action.');

    expect(User::find($admin->id))->not->toBeNull();
});

it('signs the exact bytes Guzzle transmits when the password hash contains slashes', function (): void {
    $user = User::factory()->create([
        'uuid' => fake()->uuid(),
        'name' => 'Slash User',
        'email' => 'slash@example.com',
    ])->assignRole(Role::FIELD_SUPERVISOR->value);

    $bcryptHashSample = '$2y$10$01BHATFEZX/cHLA3FadQBev.Y/j5bdtF5WFiZsx1dvTn.cEqWVTKy';
    $payload = app(SsoUserSyncService::class)->payloadForUser($user, 'updated', $bcryptHashSample);

    $transmitted = (string) json_encode($payload);
    $expected = hash_hmac('sha256', $transmitted, 'test-sync-secret');

    Http::fake([
        'https://sso.test/api/internal-sync-user' => Http::response(['message' => 'Synced'], 200),
    ]);

    app(SsoUserSyncService::class)->sync($payload);

    Http::assertSent(function (Request $request) use ($expected): bool {
        return hash_equals($expected, (string) $request->header('X-Auth-Signature')[0]);
    });
});

it('signs and posts the payload to the AuthCenter sync endpoint', function (): void {
    Http::fake([
        'https://sso.test/api/internal-sync-user' => Http::response(['message' => 'Synced'], 200),
    ]);

    $user = User::factory()->create(['uuid' => fake()->uuid(), 'name' => 'Jane Supervisor'])
        ->assignRole(Role::FIELD_SUPERVISOR->value);

    $payload = app(SsoUserSyncService::class)->payloadFor(new UserUpdated($user));

    // AuthCenter must receive a dedicated first_name so it never derives one
    // from full_name (which caused the last name to compound on each login).
    expect($payload)->toMatchArray([
        'first_name' => 'Jane',
        'last_name' => 'Supervisor',
        'full_name' => 'Jane Supervisor',
    ]);

    app(SsoUserSyncService::class)->sync($payload);

    Http::assertSent(function (Request $request): bool {
        $expected = hash_hmac('sha256', (string) json_encode($request->data()), 'test-sync-secret');

        return $request->url() === 'https://sso.test/api/internal-sync-user'
            && hash_equals($expected, (string) $request->header('X-Auth-Signature')[0]);
    });
});

it('refuses to create a customer user from the users module', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'first_name' => 'Jane',
            'last_name' => 'Engineer',
            'email' => 'jane@verizon.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'role' => Role::CUSTOMER->value,
        ])
        ->assertSessionHasErrors('role');

    expect(User::query()->where('email', 'jane@verizon.com')->exists())->toBeFalse();
});

it('refuses to demote an internal user into the customer role on edit', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $target = User::factory()->create()->assignRole(Role::MANAGER->value);

    $this->actingAs($admin)
        ->put(route('admin.users.update', $target), [
            'first_name' => $target->name,
            'last_name' => 'X',
            'email' => $target->email,
            'role' => Role::CUSTOMER->value,
        ])
        ->assertSessionHasErrors('role');

    expect($target->fresh()->hasRole(Role::MANAGER->value))->toBeTrue();
});

it('hides customer portal accounts from the users table and its role filter', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    $customer = User::factory()->create(['name' => 'Jane Smith'])->assignRole(Role::CUSTOMER->value);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where(
            'users',
            fn ($users): bool => $users->every(
                fn (array $user): bool => $user['email'] !== $customer->email
            )
        ));

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['role' => Role::CUSTOMER->value]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('users', []));
});
