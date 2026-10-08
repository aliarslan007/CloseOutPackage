<?php

declare(strict_types=1);

namespace App\Domains\Settings\Controllers;

use App\Domains\Customers\Queries\CustomerHierarchyQuery;
use App\Domains\Settings\Models\AppSetting;
use App\Domains\Settings\Requests\UpdateMailSettingsRequest;
use App\Domains\Settings\Requests\UpdateMediaQuotasRequest;
use App\Domains\Settings\Services\MailSettingsService;
use App\Domains\Settings\Services\SettingsService;
use App\Http\Controllers\Controller;
use App\Shared\Enums\Role;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AppSettingsController extends Controller
{
    public function __construct(
        private readonly SettingsService $settingsService,
        private readonly MailSettingsService $mailSettingsService,
        private readonly CustomerHierarchyQuery $customerHierarchyQuery,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', AppSetting::class);

        $user = $this->user();
        $isAdmin = $user->hasRole(Role::ADMIN->value);

        // Mail credentials and storage quotas are admin-only. They are withheld
        // here rather than hidden by the UI, so a manager never receives them
        // in the Inertia payload.
        $props = [
            'customer_hierarchy' => $this->customerHierarchyQuery->settingsPayload($user),
        ];

        if ($isAdmin) {
            $props['media_quotas'] = $this->settingsService->getMediaQuotas();
            $props['media_config'] = [
                'max_file_mb' => (int) config('cop.media_max_file_mb', 50),
                'chunk_size_mb' => (int) config('cop.media_chunk_size_mb', 5),
                'allowed_mimes' => (array) config('cop.media_allowed_mimes', []),
            ];
            $props['mail_settings'] = $this->mailSettingsService->get();
        }

        return Inertia::render('Admin/Settings/AppSettings', $props);
    }

    public function updateMailSettings(UpdateMailSettingsRequest $request): RedirectResponse
    {
        $this->mailSettingsService->save($request->toValues());

        $provider = (string) $request->validated('provider');

        $message = match ($provider) {
            MailSettingsService::PROVIDER_BREVO => 'Email sending switched to the configured Brevo account.',
            MailSettingsService::PROVIDER_SMTP => 'Email sending switched to the configured SMTP server.',
            default => 'Email sending switched to the server defaults (.env).',
        };

        return redirect()
            ->route('admin.settings.index')
            ->with('success', $message);
    }

    public function updateMediaQuotas(UpdateMediaQuotasRequest $request): RedirectResponse
    {
        $this->settingsService->setMediaQuota('admin', $this->parseQuotaBytes($request->input('admin_quota_gb')));
        $this->settingsService->setMediaQuota('manager', $this->parseQuotaBytes($request->input('manager_quota_gb')));
        $this->settingsService->setMediaQuota('supervisor', $this->parseQuotaBytes($request->input('supervisor_quota_gb')));

        return redirect()
            ->route('admin.settings.index')
            ->with('success', 'Media storage quotas updated successfully.');
    }

    private function parseQuotaBytes(mixed $quotaGb): ?int
    {
        if ($quotaGb === null || $quotaGb === '' || (float) $quotaGb <= 0) {
            return null;
        }

        return (int) round(((float) $quotaGb) * 1024 * 1024 * 1024);
    }
}
