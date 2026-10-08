<?php

declare(strict_types=1);

namespace App\Domains\Users\Services;

use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use App\Shared\Exceptions\InactiveUserException;
use App\Shared\Exceptions\SsoAccessDeniedException;
use App\Shared\Exceptions\SsoAuthException;
use App\Shared\Exceptions\TokenExchangeFailedException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SsoAuthService
{
    private const TOKEN_EXCHANGE_PATH = '/api/auth/token/exchange';

    /**
     * Redeem the SSO token and return the local user.
     *
     * @throws SsoAuthException
     */
    public function authenticate(string $token, ?string $domainId): User
    {
        $response = $this->exchangeToken($token, $domainId);

        return $this->findOrCreateUser($this->parseRemoteUser($response));
    }

    /**
     * @throws SsoAuthException
     */
    private function exchangeToken(string $token, ?string $domainId): Response
    {
        $response = Http::post($this->tokenExchangeUrl(), [
            'token' => $token,
            'domain_id' => $domainId,
            'key' => config('services.authcenter.key', 'inventory-management'),
        ]);

        if ($response->status() === 403) {
            throw new SsoAccessDeniedException;
        }

        if ($response->failed()) {
            $message = $response->json('message', 'Unknown error');

            Log::error('Token exchange failed at AuthCenter', [
                'status' => $response->status(),
                'message' => is_string($message) ? $this->sanitize($message) : 'Unknown error',
            ]);

            throw new TokenExchangeFailedException(is_string($message) ? $message : 'Unknown error');
        }

        return $response;
    }

    /**
     * @return array{email: string, uuid: ?string, name: string, password: string, roles: list<string>}
     *
     * @throws SsoAuthException
     */
    private function parseRemoteUser(Response $response): array
    {
        $payload = $response->json('user');

        if (! is_array($payload)) {
            throw new SsoAuthException('AuthCenter returned an invalid user payload.');
        }

        $email = $payload['email'] ?? null;

        if (! is_string($email)) {
            throw new SsoAuthException('AuthCenter user payload is missing the email.');
        }

        $firstName = is_string($payload['first_name'] ?? null) ? trim($payload['first_name']) : '';
        $lastName = is_string($payload['last_name'] ?? null) ? trim($payload['last_name']) : '';
        // Guarded composer: if AuthCenter already carries the last name at the
        // end of the first name (its data was polluted by a prior full_name
        // push), the duplicate is dropped instead of compounding on every login.
        $name = User::fullName($firstName, $lastName);

        if ($name === '') {
            $username = is_string($payload['username'] ?? null) ? trim($payload['username']) : '';
            $name = $username !== '' ? $username : 'Unknown User';
        }

        return [
            'email' => $email,
            'uuid' => is_string($payload['uuid'] ?? null) ? $payload['uuid'] : null,
            'name' => $name,
            'password' => is_string($payload['password'] ?? null) ? $payload['password'] : '',
            'roles' => $this->normalizeRoles($payload['roles'] ?? null),
        ];
    }

    /**
     * Normalize the AuthCenter roles claim into a flat list of role names.
     * AuthCenter may send a single role string or a list of them; anything
     * else is ignored so an unexpected payload shape degrades to a no-op.
     *
     * @return list<string>
     */
    private function normalizeRoles(mixed $roles): array
    {
        if (is_string($roles) && $roles !== '') {
            return [$roles];
        }

        if (is_array($roles)) {
            $normalized = [];

            foreach ($roles as $role) {
                if (is_string($role) && $role !== '') {
                    $normalized[] = $role;
                }
            }

            return $normalized;
        }

        return [];
    }

    /**
     * Whether AuthCenter claims the user is an admin.
     *
     * @param  list<string>  $roles
     */
    private function claimsAdmin(array $roles): bool
    {
        return in_array(Role::ADMIN->value, $roles, true);
    }

    /**
     * Keep an audit trail whenever SSO changes a user's role.
     */
    private function logRoleAssignment(User $user, string $event, string $message): void
    {
        activity('user')
            ->performedOn($user)
            ->event($event)
            ->log($message);
    }

    /**
     * Give a user without an admin claim the least-privileged working role
     * so they are never locked out of the application.
     */
    private function assignFallbackRole(User $user, string $message): void
    {
        $user->assignRole(Role::FIELD_SUPERVISOR->value);
        $this->logRoleAssignment($user, 'sso.fallback', $message);
    }

    /**
     * @param  array{email: string, uuid: ?string, name: string, password: string, roles: list<string>}  $remoteUser
     *
     * @throws InactiveUserException
     */
    private function findOrCreateUser(array $remoteUser): User
    {
        $profile = User::where('email', $remoteUser['email'])
            ->orWhere('uuid', $remoteUser['uuid'])
            ->first();

        if ($profile?->is_active === false) {
            throw new InactiveUserException;
        }

        $attributes = [
            'uuid' => $remoteUser['uuid'],
            'email' => $remoteUser['email'],
            'name' => $remoteUser['name'],
            'password' => $remoteUser['password'],
        ];

        $claimsAdmin = $this->claimsAdmin($remoteUser['roles']);

        if ($profile === null) {

            $user = User::create($attributes);

            if ($claimsAdmin) {
                $user->assignRole(Role::ADMIN->value);
                $this->logRoleAssignment($user, 'sso.admin', 'Assigned admin role from SSO claim');
            } else {
                $this->assignFallbackRole($user, 'Assigned field supervisor fallback role from SSO login');
            }

            return $user;
        }

        $profile->update($attributes);

        if ($claimsAdmin && ! $profile->hasRole(Role::ADMIN->value)) {
            $profile->syncRoles([Role::ADMIN->value]);
            $this->logRoleAssignment($profile, 'sso.admin', 'Promoted to admin role from SSO claim');
        } elseif ($profile->getRoleNames()->isEmpty()) {
            $this->assignFallbackRole($profile, 'Assigned field supervisor fallback role from SSO login');
        }

        return $profile;
    }

    /**
     * @throws SsoAuthException
     */
    private function tokenExchangeUrl(): string
    {
        $baseUrl = config('services.authcenter.url');

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new SsoAuthException('AuthCenter is not configured.');
        }

        return $baseUrl.self::TOKEN_EXCHANGE_PATH;
    }

    private function sanitize(string $value): string
    {
        return str_replace(["\n", "\r"], '', $value);
    }
}
