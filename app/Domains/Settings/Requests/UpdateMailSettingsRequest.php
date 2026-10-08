<?php

declare(strict_types=1);

namespace App\Domains\Settings\Requests;

use App\Domains\Settings\Models\AppSetting;
use App\Domains\Settings\Services\MailSettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateMailSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', AppSetting::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isSmtp = $this->input('provider') === MailSettingsService::PROVIDER_SMTP;
        $isBrevo = $this->input('provider') === MailSettingsService::PROVIDER_BREVO;

        // A stored secret satisfies the "required" credential check — the form
        // never echoes secrets, so the admin may leave the field blank.
        $hasStoredBrevoKey = is_string(AppSetting::get('mail.brevo.api_key'))
            && AppSetting::get('mail.brevo.api_key') !== '';
        $hasStoredSmtpPassword = is_string(AppSetting::get('mail.smtp.password'))
            && AppSetting::get('mail.smtp.password') !== '';

        return [
            'provider' => ['required', 'string', 'in:'.implode(',', MailSettingsService::PROVIDERS)],

            // SMTP (Gmail or any other provider)
            'host' => [$isSmtp ? 'required' : 'nullable', 'string', 'max:255'],
            'port' => [$isSmtp ? 'required' : 'nullable', 'integer', 'between:1,65535'],
            'username' => [$isSmtp ? 'required' : 'nullable', 'string', 'max:255'],
            'password' => [$isSmtp && ! $hasStoredSmtpPassword ? 'required' : 'nullable', 'string', 'max:255'],
            'encryption' => ['nullable', 'string', 'in:tls,ssl,none'],

            // Sender identity — required for both SMTP and Brevo.
            'from_address' => [($isSmtp || $isBrevo) ? 'required' : 'nullable', 'email', 'max:255'],
            'from_name' => [($isSmtp || $isBrevo) ? 'required' : 'nullable', 'string', 'max:255'],

            // Brevo
            'brevo_api_key' => [$isBrevo && ! $hasStoredBrevoKey ? 'required' : 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Values for MailSettingsService::save(). Absent optional fields become
     * null (= "no change"); empty secret = keep the stored one.
     *
     * @return array<string, mixed>
     */
    public function toValues(): array
    {
        return [
            'provider' => (string) $this->input('provider'),
            'host' => $this->input('host'),
            'port' => $this->input('port'),
            'username' => $this->input('username'),
            'password' => $this->input('password'),
            'encryption' => $this->input('encryption'),
            'from_address' => $this->input('from_address'),
            'from_name' => $this->input('from_name'),
            'brevo_api_key' => $this->input('brevo_api_key'),
        ];
    }
}
