<?php

declare(strict_types=1);

namespace App\Domains\Users\Services;

use App\Domains\Users\Contracts\ShouldSyncUserToSso;
use App\Domains\Users\Models\User;
use App\Shared\Exceptions\SsoConfigurationException;
use App\Shared\Exceptions\SsoSyncFailedException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SsoUserSyncService
{
    private const SYNC_PATH = '/api/internal-sync-user';

    /**
     * User-facing message when a credential cannot be synced. Kept deliberately
     * free of AuthCenter/HTTP internals; the technical detail goes to the log.
     */
    private const SYNC_UNAVAILABLE_MESSAGE = 'We could not sync this change with the authentication service, so nothing was updated. Please try again later.';

    /**
     * Build the payload that the AuthCenter integration endpoint expects.
     *
     * @return array<string, string>
     */
    public function payloadForUser(User $user, string $action, ?string $passwordOverride = null): array
    {
        $role = $user->getRoleNames()->first();
        $userOrigin = config('services.authcenter.user_origin', 'solucomp_cop');

        $password = $passwordOverride ?? (string) $user->password;
        $password = Hash::needsRehash($password) ? Hash::make($password) : $password;

        return [
            'uuid' => (string) $user->uuid,
            // A dedicated first_name (with last_name) is sent so AuthCenter
            // never has to derive the first name from `full_name` — deriving it
            // there caused the last name to compound on every login cycle.
            'first_name' => Str::beforeLast((string) $user->name, ' '),
            'last_name' => Str::afterLast((string) $user->name, ' '),
            'full_name' => (string) $user->name,
            'role' => is_string($role) && $role !== '' ? $role : 'user',
            'personal_email' => (string) $user->email,
            'password' => $password,
            'action' => $action,
            'user_origin' => is_string($userOrigin) && $userOrigin !== '' ? $userOrigin : 'solucomp_cop',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function payloadFor(ShouldSyncUserToSso $event): array
    {
        return $this->payloadForUser($event->user(), $event->ssoAction());
    }

    /**
     * Push a credential change now and fail loudly.
     *
     * Email and password are the login keys AuthCenter authorises against, so
     * they must never drift from this app. Callers invoke this inside a DB
     * transaction: a throw rolls the local change back and surfaces the message
     * below instead of leaving the two systems disagreeing.
     *
     * ponytail: keeps the DB transaction open for the duration of the HTTP call
     * (10s timeout); acceptable for a single user-initiated edit.
     *
     * @throws SsoSyncFailedException
     */
    public function syncNow(User $user): void
    {
        try {
            $this->sync($this->payloadForUser($user, 'updated'));
        } catch (SsoConfigurationException|SsoSyncFailedException|ConnectionException $e) {
            Log::error('AuthCenter credential sync failed', [
                'user_uuid' => (string) $user->uuid,
                'error' => $e->getMessage(),
            ]);

            throw new SsoSyncFailedException(self::SYNC_UNAVAILABLE_MESSAGE);
        }
    }

    /**
     * Push the payload to the AuthCenter integration endpoint.
     *
     * @param  array<string, string>  $payload
     *
     * @throws SsoSyncFailedException
     */
    public function sync(array $payload): void
    {
        $baseUrl = config('services.authcenter.url');
        $secret = config('services.sync.secret');

        if (! is_string($baseUrl) || $baseUrl === '' || ! is_string($secret) || $secret === '') {
            throw new SsoConfigurationException('AuthCenter sync is not configured.');
        }

        $secretKey = Str::startsWith($secret, 'base64:') ? base64_decode(substr($secret, 7), true) : $secret;

        if (! is_string($secretKey) || $secretKey === '') {
            throw new SsoConfigurationException('AuthCenter sync secret is invalid.');
        }

        $signature = hash_hmac('sha256', (string) json_encode($payload, JSON_THROW_ON_ERROR), $secretKey);

        $response = Http::timeout(10)
            ->withHeaders(['X-Auth-Signature' => $signature])
            ->acceptJson()
            ->post($baseUrl.self::SYNC_PATH, $payload);

        if ($response->failed()) {
            Log::warning('AuthCenter sync rejected', [
                'url' => $baseUrl.self::SYNC_PATH,
                'status' => $response->status(),
                'body' => $response->body(),
                'action' => $payload['action'] ?? null,
                'user_uuid' => $payload['uuid'] ?? null,
                'key_fingerprint' => substr(hash('sha256', $secretKey), 0, 16),
                'signature_prefix' => substr($signature, 0, 16),
            ]);

            throw new SsoSyncFailedException(sprintf(
                'AuthCenter sync failed (HTTP %d): %s',
                $response->status(),
                $response->body(),
            ));
        }
    }
}
