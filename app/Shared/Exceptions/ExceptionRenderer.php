<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use App\Domains\Email\EmailScheduleInPastException;
use App\Domains\Email\EmailScheduleTooFarAheadException;
use App\Domains\Email\InvalidOutboxTransitionException;
use App\Shared\Exceptions\AuthorizationException as CopAuthorizationException;
use Illuminate\Auth\Access\AuthorizationException as LaravelAuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

/**
 * Renders exceptions into user-facing HTTP responses. Registered as render
 * callbacks in bootstrap/app.php; the callbacks keep their typed first
 * parameter so Laravel dispatches each exception to the right renderer.
 */
final class ExceptionRenderer
{
    public function render(Throwable $exception, Request $request): JsonResponse|RedirectResponse|Response|null
    {
        if ($exception instanceof AuthenticationException) {
            return $this->unauthenticated($request);
        }

        if ($exception instanceof CopException) {
            return $this->domain($exception, $request);
        }

        if (
            $exception instanceof LaravelAuthorizationException
            || $exception instanceof AccessDeniedHttpException
            || $exception instanceof UnauthorizedException
        ) {
            return $this->permissionDenied($request);
        }

        // Fall through to Laravel's default exception handling.
        return null;
    }

    private function domain(CopException $exception, Request $request): JsonResponse|RedirectResponse|Response
    {
        $status = $this->statusFor($exception);

        if ($status >= 500) {
            Log::error('Unhandled domain exception', [
                'exception' => $exception::class,
                'message' => str_replace(["\n", "\r"], ' ', $exception->getMessage()),
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'errors' => new \stdClass,
            ], $status);
        }

        if ($exception instanceof CopAuthorizationException) {
            return $this->permissionDenied($request);
        }

        if ($status >= 500) {
            abort($status, $exception->getMessage());
        }

        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        abort($status, $exception->getMessage());
    }

    private function unauthenticated(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return redirect()
            ->guest(route('session.expired'))
            ->with('error', 'Your session has expired. Please sign in again to continue.');
    }

    private function permissionDenied(Request $request): JsonResponse|RedirectResponse
    {
        $message = 'You do not have permission to perform this action.';

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], Response::HTTP_FORBIDDEN);
        }

        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return redirect()->back()->with('error', $message);
        }

        abort(Response::HTTP_FORBIDDEN, $message);
    }

    private function statusFor(CopException $exception): int
    {
        return match (true) {
            $exception instanceof ConflictException => Response::HTTP_CONFLICT,
            $exception instanceof WorkflowException,
            $exception instanceof BusinessRuleException,
            $exception instanceof DomainException,
            $exception instanceof MediaException,
            $exception instanceof EmailNotConfiguredException,
            $exception instanceof SsoSyncFailedException,
            $exception instanceof InvalidOutboxTransitionException,
            $exception instanceof EmailScheduleInPastException,
            $exception instanceof EmailScheduleTooFarAheadException => Response::HTTP_UNPROCESSABLE_ENTITY,
            $exception instanceof UploadTokenException => Response::HTTP_UNAUTHORIZED,
            $exception instanceof MediaStorageUnavailableException => Response::HTTP_INSUFFICIENT_STORAGE,
            $exception instanceof CopAuthorizationException => Response::HTTP_FORBIDDEN,
            default => Response::HTTP_INTERNAL_SERVER_ERROR,
        };
    }
}
