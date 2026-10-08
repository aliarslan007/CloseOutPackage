<?php

declare(strict_types=1);

namespace App\Domains\Email\Services;

use App\Domains\Email\DTOs\SendEmailDTO;
use App\Domains\Email\Mailables\DynamicEmailMailable;
use App\Domains\Settings\Services\MailSettingsService;
use App\Shared\Exceptions\EmailDeliveryException;
use Illuminate\Support\Facades\Mail;

/**
 * Chooses the mail transport per send based on admin settings:
 *  1. Brevo API (AppSetting: mail.brevo.api_key + sender identity)
 *  2. SMTP (AppSetting: mail.smtp.* + runtime cop_smtp mailer)
 *  3. Server Default (.env / config: Brevo if BREVO_API_KEY in .env, otherwise default mailer)
 */
final class DynamicMailSender
{
    public function __construct(
        private readonly MailSettingsService $mailSettings,
        private readonly BrevoEmailSender $brevoSender,
    ) {}

    /**
     * Sends through the configured transport.
     *
     * @return string|null the provider message id when the transport supplies
     *                     one (Brevo), null otherwise (SMTP / Env mailer)
     */
    public function send(SendEmailDTO $dto): ?string
    {
        $settings = $this->mailSettings->get();
        $provider = $settings['provider'];

        if ($provider === MailSettingsService::PROVIDER_BREVO) {
            return $this->sendViaBrevo($dto, $settings);
        }

        if ($provider === MailSettingsService::PROVIDER_SMTP) {
            return $this->sendViaSmtp($dto, $settings);
        }

        return $this->sendViaEnv($dto);
    }

    /**
     * @param  array{provider: string, smtp: array{host: string, port: int, username: string, encryption: string, from_address: string, from_name: string}, has_smtp_password: bool, has_brevo_api_key: bool}  $settings
     */
    private function sendViaBrevo(SendEmailDTO $dto, array $settings): ?string
    {
        $secrets = $this->mailSettings->getSecrets();
        $key = $secrets['brevo_api_key'];

        if (! is_string($key) || $key === '') {
            throw new EmailDeliveryException('Brevo API key is not configured in Email Settings.');
        }

        $fromAddress = $settings['smtp']['from_address'] !== ''
            ? $settings['smtp']['from_address']
            : (string) config('mail.from.address');

        $fromName = $settings['smtp']['from_name'] !== ''
            ? $settings['smtp']['from_name']
            : (string) config('mail.from.name');

        if ($fromAddress === '') {
            throw new EmailDeliveryException('From Email Address is required for Brevo.');
        }

        return $this->brevoSender->send($dto, $key, $fromAddress, $fromName);
    }

    /**
     * @param  array{provider: string, smtp: array{host: string, port: int, username: string, encryption: string, from_address: string, from_name: string}, has_smtp_password: bool, has_brevo_api_key: bool}  $settings
     */
    private function sendViaSmtp(SendEmailDTO $dto, array $settings): ?string
    {
        $smtp = $settings['smtp'];

        if ($smtp['host'] === '' || $smtp['username'] === '') {
            throw new EmailDeliveryException('SMTP settings are incomplete. Please configure host and username.');
        }

        $password = $this->mailSettings->getSecrets()['smtp_password'];

        if (! is_string($password) || $password === '') {
            throw new EmailDeliveryException('SMTP password is not configured.');
        }

        $encryption = $smtp['encryption'] !== '' && $smtp['encryption'] !== 'none'
            ? $smtp['encryption']
            : null;

        config([
            'mail.mailers.cop_smtp' => [
                'transport' => 'smtp',
                'host' => $smtp['host'],
                'port' => $smtp['port'],
                'username' => $smtp['username'],
                'password' => $password,
                'encryption' => $encryption,
                'timeout' => 30,
            ],
        ]);

        $fromAddress = $smtp['from_address'] !== '' ? $smtp['from_address'] : (string) config('mail.from.address');
        $fromName = $smtp['from_name'] !== '' ? $smtp['from_name'] : (string) config('mail.from.name');

        $mailable = new DynamicEmailMailable($dto, $fromAddress, $fromName);

        Mail::mailer('cop_smtp')->send($mailable);

        return null;
    }

    private function sendViaEnv(SendEmailDTO $dto): ?string
    {
        $envBrevoKey = config('services.brevo.key');

        if (is_string($envBrevoKey) && $envBrevoKey !== '') {
            return $this->brevoSender->send($dto, $envBrevoKey);
        }

        $defaultMailer = (string) config('mail.default', 'log');
        $fromAddress = (string) config('mail.from.address');
        $fromName = (string) config('mail.from.name');

        $mailable = new DynamicEmailMailable($dto, $fromAddress, $fromName);

        Mail::mailer($defaultMailer)->send($mailable);

        return null;
    }
}
