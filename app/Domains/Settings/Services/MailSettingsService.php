<?php

declare(strict_types=1);

namespace App\Domains\Settings\Services;

use App\Domains\Settings\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Single source of truth for the dynamically configured mail transport.
 *
 * Stored in the encrypted AppSetting store (never .env) so admins can switch
 * providers at runtime. `provider = env` means "use .env/config defaults".
 */
final class MailSettingsService
{
    public const PROVIDER_ENV = 'env';

    public const PROVIDER_BREVO = 'brevo';

    public const PROVIDER_SMTP = 'smtp';

    /** @var list<string> */
    public const PROVIDERS = [self::PROVIDER_ENV, self::PROVIDER_BREVO, self::PROVIDER_SMTP];

    /** Gmail's fixed SMTP endpoint — preset for the Gmail tab. */
    public const GMAIL_HOST = 'smtp.gmail.com';

    public const GMAIL_PORT = 587;

    public const GMAIL_ENCRYPTION = 'tls';

    private const CACHE_KEY = 'settings.mail';

    private const CACHE_TTL_SECONDS = 300;

    /**
     * Runtime shape handed to the sender + settings form. Secrets are never
     * serialized to the client — use hasSmtpPassword()/hasBrevoApiKey().
     *
     * @return array{provider: string, smtp: array{host: string, port: int, username: string, encryption: string, from_address: string, from_name: string}, has_smtp_password: bool, has_brevo_api_key: bool}
     */
    public function get(): array
    {
        /** @var array{provider: string, smtp: array{host: string, port: int, username: string, encryption: string, from_address: string, from_name: string}, has_smtp_password: bool, has_brevo_api_key: bool} $settings */
        $settings = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn (): array => $this->load());

        return $settings;
    }

    /**
     * Effective sender identity configured for transactional emails.
     *
     * @return array{address: string, name: string}
     */
    public function getSender(): array
    {
        $settings = $this->get();
        $fromAddress = $settings['smtp']['from_address'] !== ''
            ? $settings['smtp']['from_address']
            : (string) (config('mail.from.address') ?? '');

        $fromName = $settings['smtp']['from_name'] !== ''
            ? $settings['smtp']['from_name']
            : (string) (config('mail.from.name') ?? '');

        return [
            'address' => $fromAddress,
            'name' => $fromName,
        ];
    }

    /**
     * Secrets for the transport layer (never sent to the browser).
     *
     * @return array{smtp_password: ?string, brevo_api_key: ?string}
     */
    public function getSecrets(): array
    {
        return [
            'smtp_password' => AppSetting::get('mail.smtp.password'),
            'brevo_api_key' => AppSetting::get('mail.brevo.api_key'),
        ];
    }

    /**
     * Persist one provider's config. Null password/secret leaves the stored
     * one untouched (the form never round-trips secrets).
     *
     * @param  array{provider: string, host?: ?string, port?: ?int, username?: ?string, password?: ?string, encryption?: ?string, from_address?: ?string, from_name?: ?string, brevo_api_key?: ?string}  $values
     */
    public function save(array $values): void
    {
        $provider = $values['provider'];

        AppSetting::set('mail.provider', $provider);

        if (array_key_exists('brevo_api_key', $values) && $values['brevo_api_key'] !== null && $values['brevo_api_key'] !== '') {
            AppSetting::set('mail.brevo.api_key', $values['brevo_api_key']);
        }

        // Sender identity and SMTP settings
        $smtpKeys = [
            'host' => 'mail.smtp.host',
            'port' => 'mail.smtp.port',
            'username' => 'mail.smtp.username',
            'encryption' => 'mail.smtp.encryption',
            'from_address' => 'mail.smtp.from_address',
            'from_name' => 'mail.smtp.from_name',
        ];

        foreach ($smtpKeys as $input => $key) {
            if (array_key_exists($input, $values)) {
                $value = $values[$input];
                if ($value !== null && $value !== '') {
                    AppSetting::set($key, (string) $value);
                }
            }
        }

        // Empty password = keep existing (secrets are not echoed to the form).
        if (array_key_exists('password', $values) && $values['password'] !== null && $values['password'] !== '') {
            AppSetting::set('mail.smtp.password', $values['password']);
        }

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{provider: string, smtp: array{host: string, port: int, username: string, encryption: string, from_address: string, from_name: string}, has_smtp_password: bool, has_brevo_api_key: bool}
     */
    private function load(): array
    {
        $provider = AppSetting::get('mail.provider') ?? self::PROVIDER_ENV;

        $fromAddressDefault = is_string(config('mail.from.address')) ? config('mail.from.address') : '';
        $fromNameDefault = is_string(config('mail.from.name')) ? config('mail.from.name') : '';

        $smtp = [
            'host' => (string) (AppSetting::get('mail.smtp.host') ?? ''),
            'port' => (int) (AppSetting::get('mail.smtp.port') ?? 587),
            'username' => (string) (AppSetting::get('mail.smtp.username') ?? ''),
            'encryption' => (string) (AppSetting::get('mail.smtp.encryption') ?? 'tls'),
            'from_address' => (string) (AppSetting::get('mail.smtp.from_address') ?? $fromAddressDefault),
            'from_name' => (string) (AppSetting::get('mail.smtp.from_name') ?? $fromNameDefault),
        ];

        $hasSmtpPassword = is_string(AppSetting::get('mail.smtp.password')) && AppSetting::get('mail.smtp.password') !== '';
        $hasBrevoApiKey = is_string(AppSetting::get('mail.brevo.api_key')) && AppSetting::get('mail.brevo.api_key') !== '';

        return [
            'provider' => in_array($provider, self::PROVIDERS, true) ? $provider : self::PROVIDER_ENV,
            'smtp' => $smtp,
            'has_smtp_password' => $hasSmtpPassword,
            'has_brevo_api_key' => $hasBrevoApiKey,
        ];
    }
}
