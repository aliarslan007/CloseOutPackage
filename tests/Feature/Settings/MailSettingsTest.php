<?php

declare(strict_types=1);

use App\Domains\Email\DTOs\SendEmailDTO;
use App\Domains\Email\Enums\EmailType;
use App\Domains\Email\Mailables\DynamicEmailMailable;
use App\Domains\Email\Services\DynamicMailSender;
use App\Domains\Settings\Services\MailSettingsService;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('saves and reloads smtp mail settings, keeping the password when omitted', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    actingAs($admin);

    $service = app(MailSettingsService::class);

    $service->save([
        'provider' => MailSettingsService::PROVIDER_SMTP,
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => 'ops@company.com',
        'password' => 'app-password-123',
        'encryption' => 'tls',
        'from_address' => 'noreply@company.com',
        'from_name' => 'Close Out Package',
    ]);

    $settings = $service->get();

    expect($settings['provider'])->toBe(MailSettingsService::PROVIDER_SMTP)
        ->and($settings['smtp']['host'])->toBe('smtp.gmail.com')
        ->and($settings['smtp']['port'])->toBe(587)
        ->and($settings['smtp']['username'])->toBe('ops@company.com')
        ->and($settings['smtp']['from_address'])->toBe('noreply@company.com')
        ->and($settings['has_smtp_password'])->toBeTrue()
        // Secrets are never exposed in the client-facing shape.
        ->and($settings)->not->toHaveKey('password');

    // Saving again without a password keeps the stored one.
    $service->save([
        'provider' => MailSettingsService::PROVIDER_SMTP,
        'host' => 'smtp.other.com',
        'port' => 465,
        'username' => 'ops@company.com',
        'password' => null,
        'encryption' => 'ssl',
        'from_address' => 'noreply@company.com',
        'from_name' => 'Close Out Package',
    ]);

    $reloaded = $service->get();

    expect($reloaded['smtp']['host'])->toBe('smtp.other.com')
        ->and($reloaded['smtp']['port'])->toBe(465)
        ->and($reloaded['has_smtp_password'])->toBeTrue()
        ->and($service->getSecrets()['smtp_password'])->toBe('app-password-123');
});

it('persists the brevo provider with an api key', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);
    actingAs($admin);

    $service = app(MailSettingsService::class);

    $service->save([
        'provider' => MailSettingsService::PROVIDER_BREVO,
        'brevo_api_key' => 'xkeysib-test-key',
    ]);

    expect($service->get()['provider'])->toBe(MailSettingsService::PROVIDER_BREVO)
        ->and($service->get()['has_brevo_api_key'])->toBeTrue()
        ->and($service->getSecrets()['brevo_api_key'])->toBe('xkeysib-test-key');
});

it('rejects non-admin users via policy', function (): void {
    $manager = User::factory()->create()->assignRole(Role::MANAGER->value);

    actingAs($manager)
        ->post(route('admin.settings.mail.update'), ['provider' => 'smtp'])
        ->assertRedirect();

    // Nothing was persisted.
    expect(app(MailSettingsService::class)->get()['provider'])->toBe(MailSettingsService::PROVIDER_ENV);
});

it('saves brevo via HTTP with only api key and sender identity (no SMTP fields sent)', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    actingAs($admin)
        ->post(route('admin.settings.mail.update'), [
            'provider' => 'brevo',
            'brevo_api_key' => 'xkeysib-http-test',
            'from_address' => 'noreply@company.com',
            'from_name' => 'Close Out Package',
        ])
        ->assertRedirect(route('admin.settings.index'))
        ->assertSessionHas('success');

    $service = app(MailSettingsService::class);

    expect($service->get()['provider'])->toBe('brevo')
        ->and($service->getSecrets()['brevo_api_key'])->toBe('xkeysib-http-test')
        ->and($service->get()['smtp']['from_address'])->toBe('noreply@company.com');
});

it('requires the brevo api key when switching to brevo with none stored', function (): void {
    $admin = User::factory()->create()->assignRole(Role::ADMIN->value);

    actingAs($admin)
        ->post(route('admin.settings.mail.update'), [
            'provider' => 'brevo',
            'from_address' => 'noreply@company.com',
            'from_name' => 'Close Out Package',
        ])
        ->assertSessionHasErrors('brevo_api_key');

    expect(app(MailSettingsService::class)->get()['provider'])->toBe('env');
});

it('uses the dynamically configured brevo key instead of the env one at send time', function (): void {
    config(['services.brevo.key' => 'env-fallback-key']);

    app(MailSettingsService::class)->save([
        'provider' => MailSettingsService::PROVIDER_BREVO,
        'brevo_api_key' => 'dynamic-key',
        'from_address' => 'dynamic@company.com',
        'from_name' => 'Dynamic Sender',
    ]);

    Http::fake([
        'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'm77'], 201),
    ]);

    $dto = new SendEmailDTO(
        to: 'client@example.com',
        subject: 'Test',
        type: EmailType::GENERAL,
        body: 'Test body',
    );

    app(DynamicMailSender::class)->send($dto);

    Http::assertSent(function (HttpRequest $request): bool {
        $payload = $request->data();

        return $request->header('api-key') === ['dynamic-key']
            && $payload['sender']['email'] === 'dynamic@company.com'
            && $payload['sender']['name'] === 'Dynamic Sender';
    });
});

it('uses the dynamically configured smtp server at send time', function (): void {
    app(MailSettingsService::class)->save([
        'provider' => MailSettingsService::PROVIDER_SMTP,
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => 'ops@company.com',
        'password' => 'app-pass',
        'encryption' => 'tls',
        'from_address' => 'smtp-from@company.com',
        'from_name' => 'SMTP Sender',
    ]);

    Mail::fake();

    $dto = new SendEmailDTO(
        to: 'client@example.com',
        subject: 'Test',
        type: EmailType::GENERAL,
        body: 'Test body',
    );

    app(DynamicMailSender::class)->send($dto);

    Mail::assertSent(DynamicEmailMailable::class, function (DynamicEmailMailable $mail): bool {
        return $mail->hasTo('client@example.com')
            && $mail->hasFrom('smtp-from@company.com', 'SMTP Sender');
    });
});

it('sends via server default env mailer when provider is env', function (): void {
    config(['services.brevo.key' => null]);

    app(MailSettingsService::class)->save([
        'provider' => MailSettingsService::PROVIDER_ENV,
    ]);

    Mail::fake();

    $dto = new SendEmailDTO(
        to: 'client@example.com',
        subject: 'Env Test',
        type: EmailType::GENERAL,
        body: 'Test body env',
    );

    app(DynamicMailSender::class)->send($dto);

    Mail::assertSent(DynamicEmailMailable::class, function (DynamicEmailMailable $mail): bool {
        return $mail->hasTo('client@example.com');
    });
});
