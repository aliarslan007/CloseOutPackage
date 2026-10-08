<?php

declare(strict_types=1);

use App\Domains\Users\Jobs\SyncUserToSsoJob;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();

    config()->set('services.authcenter.url', 'https://sso.test');
    config()->set('services.authcenter.user_origin', 'solucomp_cop');
    config()->set('services.sync.secret', 'test-sync-secret');
});

it('displays the profile page', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/profile')
        ->assertOk();
});

it('updates profile information and syncs to AuthCenter SSO', function (): void {
    Queue::fake();

    $user = User::factory()->create(['name' => 'Test User']);

    $this->actingAs($user)
        ->patch('/profile', [
            'first_name' => 'John',
            'last_name' => 'Field',
            'email' => $user->email,
            'company' => 'Verizon Wireless',
            'phone' => '(123) 456-7890',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile')
        ->assertSessionHas('success');

    $user->refresh();

    expect($user->name)->toBe('John Field')
        ->and($user->company)->toBe('Verizon Wireless')
        ->and($user->phone)->toBe('(123) 456-7890')
        ->and($user->email_verified_at)->not->toBeNull();

    Queue::assertPushed(SyncUserToSsoJob::class, fn (SyncUserToSsoJob $job): bool => $job->payload['action'] === 'updated'
        && $job->payload['full_name'] === 'John Field'
        && $job->payload['first_name'] === 'John'
        && $job->payload['last_name'] === 'Field'
        && $job->payload['personal_email'] === $user->email);
});

it('unverifies the email address when it is changed and syncs new email to AuthCenter SSO', function (): void {
    Queue::fake();

    Http::fake([
        'https://sso.test/api/internal-sync-user' => Http::response(['message' => 'Synced'], 200),
    ]);

    $user = User::factory()->create(['name' => 'Test User']);

    $this->actingAs($user)
        ->patch('/profile', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'new@example.com',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    expect($user->email)->toBe('new@example.com')
        ->and($user->email_verified_at)->toBeNull();

    // Email is synced synchronously, so the async job must not also fire.
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://sso.test/api/internal-sync-user'
        && $request['action'] === 'updated'
        && $request['personal_email'] === 'new@example.com');

    Queue::assertNotPushed(SyncUserToSsoJob::class);
});

it('rolls back an email change when the AuthCenter is unreachable', function (): void {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $user = User::factory()->create(['name' => 'Test User', 'email' => 'old@example.com']);

    $this->actingAs($user)
        ->from('/profile')
        ->patch('/profile', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'new@example.com',
        ])
        ->assertRedirect('/profile')
        ->assertSessionHas('error', 'We could not sync this change with the authentication service, so nothing was updated. Please try again later.');

    expect($user->fresh()->email)->toBe('old@example.com');
});

it('clears company and phone when emptied on the profile page', function (): void {
    Queue::fake();

    $user = User::factory()->create([
        'name' => 'Test User',
        'company' => 'Old Co',
        'phone' => '(111) 222-3333',
    ]);

    $this->actingAs($user)
        ->patch('/profile', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $user->email,
            'company' => '',
            'phone' => '',
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->company)->toBeNull()
        ->and($user->phone)->toBeNull();
});

it('validates company length and phone format on the profile page', function (): void {
    Queue::fake();

    $user = User::factory()->create(['name' => 'Test User']);

    $this->actingAs($user)
        ->patch('/profile', [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $user->email,
            'company' => str_repeat('a', 51),
            'phone' => 'not-a-phone',
        ])
        ->assertSessionHasErrors(['company', 'phone']);

    $user->refresh();

    expect($user->company)->not->toBe(str_repeat('a', 51))
        ->and($user->phone)->not->toBe('not-a-phone');
});

it('replaces the profile photo from the profile page', function (): void {
    Storage::fake('private');
    Queue::fake();

    $user = User::factory()->create(['name' => 'Test User']);
    $user->addMedia(UploadedFile::fake()->image('old.jpg'))->toMediaCollection(User::PROFILE_PHOTO_COLLECTION);
    $old = $user->getFirstMedia(User::PROFILE_PHOTO_COLLECTION);

    // Mirrors the frontend, which sends _method=patch via a POST so PHP can parse
    // the multipart body (a true PATCH would lose every field when a file is present).
    $this->actingAs($user)
        ->post('/profile', [
            '_method' => 'patch',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $user->email,
            'profile_photo' => UploadedFile::fake()->image('new.jpg'),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    expect($user->getFirstMedia(User::PROFILE_PHOTO_COLLECTION)?->id)->not->toBe($old->id);
    Queue::assertPushed(SyncUserToSsoJob::class);
});

it('updates the password from the profile page and syncs hash to AuthCenter SSO', function (): void {
    Queue::fake();

    Http::fake([
        'https://sso.test/api/internal-sync-user' => Http::response(['message' => 'Synced'], 200),
    ]);

    $user = User::factory()->create([
        'password' => Hash::make('old-password-123'),
    ]);

    $this->actingAs($user)
        ->put('/password', [
            'current_password' => 'old-password-123',
            'password' => 'new-secure-password-456',
            'password_confirmation' => 'new-secure-password-456',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect()
        ->assertSessionHas('success');

    $user->refresh();

    expect(Hash::check('new-secure-password-456', $user->password))->toBeTrue();

    // Password is synced synchronously, so the async job must not also fire.
    Http::assertSent(fn (Request $request): bool => $request['action'] === 'updated'
        && Hash::check('new-secure-password-456', $request['password']));

    Queue::assertNotPushed(SyncUserToSsoJob::class);
});

it('rolls back a password change when the AuthCenter sync fails', function (): void {
    Http::fake([
        'https://sso.test/api/internal-sync-user' => Http::response('nope', 500),
    ]);

    $user = User::factory()->create([
        'password' => Hash::make('old-password-123'),
    ]);

    $this->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'old-password-123',
            'password' => 'new-secure-password-456',
            'password_confirmation' => 'new-secure-password-456',
        ])
        ->assertRedirect('/profile')
        ->assertSessionHas('error', 'We could not sync this change with the authentication service, so nothing was updated. Please try again later.');

    expect(Hash::check('old-password-123', $user->fresh()->password))->toBeTrue();
});

it('requires the correct current password to update password', function (): void {
    Queue::fake();

    $user = User::factory()->create([
        'password' => Hash::make('old-password-123'),
    ]);

    $this->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'wrong-current-password',
            'password' => 'new-secure-password-456',
            'password_confirmation' => 'new-secure-password-456',
        ])
        ->assertSessionHasErrors('current_password')
        ->assertRedirect('/profile');

    $user->refresh();

    expect(Hash::check('old-password-123', $user->password))->toBeTrue();
    Queue::assertNotPushed(SyncUserToSsoJob::class);
});

it('validates password confirmation and minimum length on password update', function (): void {
    Queue::fake();

    $user = User::factory()->create([
        'password' => Hash::make('old-password-123'),
    ]);

    $this->actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'old-password-123',
            'password' => 'short',
            'password_confirmation' => 'mismatched',
        ])
        ->assertSessionHasErrors('password')
        ->assertRedirect('/profile');

    Queue::assertNotPushed(SyncUserToSsoJob::class);
});

it('deletes the user account', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    expect(User::find($user->id))->toBeNull();
});

it('requires the correct password to delete the account', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/profile')
        ->delete('/profile', [
            'password' => 'wrong-password',
        ])
        ->assertSessionHasErrors('password')
        ->assertRedirect('/profile');

    expect(User::find($user->id))->not->toBeNull();
});
