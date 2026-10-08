<?php

declare(strict_types=1);

namespace App\Domains\Users\Controllers;

use App\Domains\Users\Services\SsoAuthService;
use App\Http\Controllers\Controller;
use App\Shared\Exceptions\InactiveUserException;
use App\Shared\Exceptions\SsoAccessDeniedException;
use App\Shared\Exceptions\SsoAuthException;
use App\Shared\Exceptions\SsoConfigurationException;
use App\Shared\Exceptions\TokenExchangeFailedException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SsoAuthController extends Controller
{
    public function __construct(private readonly SsoAuthService $ssoAuthService) {}

    /**
     * Redirect the user to the external SSO provider.
     */
    public function redirect(Request $request): RedirectResponse
    {
        try {
            $url = $this->ssoUrl('authorization_url');
        } catch (SsoConfigurationException) {
            return redirect()->route('session.expired')
                ->with('error', 'SSO login is not configured. Please contact your administrator.');
        }

        return redirect()->away($url);
    }

    /**
     * Handle the SSO callback, resolve the user, and start a session.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $token = $request->string('token')->toString();

        if ($token === '') {
            return $this->loginFailure('SSO login failed: Missing token.');
        }

        $domainId = $request->query('domain_ids');

        try {
            $user = $this->ssoAuthService->authenticate($token, is_string($domainId) ? $domainId : null);
        } catch (SsoAuthException $exception) {
            return $this->failureRedirect($exception);
        }

        Auth::login($user);

        return $this->loginSuccess($request);
    }

    /**
     * Log the user out locally and land on the in-app welcome page. An
     * external redirect here would break the Inertia XHR (CORS), leaving the
     * user on an unhandled error page.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('welcome');
    }

    private function loginSuccess(Request $request): RedirectResponse
    {
        if ($request->has('redirect_to')) {
            $redirectTo = $request->string('redirect_to')->toString();

            if (Str::startsWith($redirectTo, '/') && ! Str::startsWith($redirectTo, '//')) {
                return redirect($redirectTo)->with('success', 'Login successful!');
            }
        }

        return redirect()->route($this->user()->landingRoute())->with('success', 'Login successful!');
    }

    private function failureRedirect(SsoAuthException $exception): RedirectResponse
    {
        return match (true) {
            $exception instanceof SsoAccessDeniedException => $this->loginFailure('Access denied: You are not authorized for this domain.'),
            $exception instanceof TokenExchangeFailedException => $this->loginFailure('SSO login failed: '.$exception->getMessage()),
            $exception instanceof InactiveUserException => $this->inactiveFailure(),
            default => $this->unexpectedFailure($exception),
        };
    }

    private function unexpectedFailure(SsoAuthException $exception): RedirectResponse
    {
        Log::critical('SSO callback failed', ['message' => $this->sanitize($exception->getMessage())]);

        return $this->loginFailure('Unexpected error occurred during SSO login.');
    }

    private function loginFailure(string $message): RedirectResponse
    {
        $redirectUrl = config('services.redirect.url');

        if (is_string($redirectUrl) && $redirectUrl !== '') {
            return redirect()->away($redirectUrl);
        }

        return redirect()->route('dashboard')->with('error', $message);
    }

    private function inactiveFailure(): RedirectResponse
    {
        $redirectUrl = config('services.redirect.url');

        if (is_string($redirectUrl) && $redirectUrl !== '') {
            return redirect()->away($redirectUrl.'?error=Account inactive');
        }

        return redirect()
            ->route('dashboard')
            ->with('error', 'Your account has been deactivated. Please contact an administrator.');
    }

    /**
     * Return a configured SSO URL, failing loudly when missing.
     */
    private function ssoUrl(string $key): string
    {
        $url = config('sso.'.$key);

        if (! is_string($url) || $url === '') {
            throw new SsoConfigurationException('Missing SSO configuration: sso.'.$key);
        }

        return $url;
    }

    private function sanitize(string $value): string
    {
        return str_replace(["\n", "\r"], '', $value);
    }
}
