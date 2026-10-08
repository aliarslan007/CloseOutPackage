<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\NormalizeLineEndings;
use App\Shared\Exceptions\CopException;
use App\Shared\Exceptions\ExceptionRenderer;
use Illuminate\Auth\Access\AuthorizationException as LaravelAuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withEvents(discover: [
        __DIR__.'/../app/Domains/*/Listeners',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            // Must precede every FormRequest so `max:` rules measure the same
            // characters the field's counter showed the user.
            NormalizeLineEndings::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $render = fn (Throwable $exception, Request $request) => app(ExceptionRenderer::class)->render($exception, $request);

        $exceptions->render(fn (CopException $exception, Request $request) => $render($exception, $request));
        $exceptions->render(fn (LaravelAuthorizationException $exception, Request $request) => $render($exception, $request));
        $exceptions->render(fn (AccessDeniedHttpException $exception, Request $request) => $render($exception, $request));
        $exceptions->render(fn (UnauthorizedException $exception, Request $request) => $render($exception, $request));
        $exceptions->render(fn (AuthenticationException $exception, Request $request) => $render($exception, $request));
    })->create();
