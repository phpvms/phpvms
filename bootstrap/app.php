<?php

use App\Exceptions\AbstractHttpException;
use App\Exceptions\AssetNotFound;
use App\Exceptions\Converters\GenericExceptionAbstract;
use App\Exceptions\Converters\SymfonyException;
use App\Exceptions\Converters\ValidationException;
use App\Exceptions\Unauthenticated;
use App\Http\Middleware\ApiAuth;
use App\Http\Middleware\DisableActivityLoggingByDefault;
use App\Http\Middleware\InstalledCheck;
use App\Http\Middleware\SetActiveLanguage;
use App\Http\Middleware\SetActiveTheme;
use App\Http\Middleware\UpdatePending;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException as IlluminateValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpException as SymfonyHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Prepend the activity-logging reset to both groups so every request
        // starts with logging disabled. EnableActivityLogging (and Filament's
        // panel boot hook) then opt in for the request that wants it. Required
        // for Octane safety — see config/octane.php and the change at
        // openspec/changes/switch-to-frankenphp-image/design.md.
        $middleware->prependToGroup('web', DisableActivityLoggingByDefault::class);
        $middleware->prependToGroup('api', DisableActivityLoggingByDefault::class);

        $middleware->appendToGroup('web', [
            InstalledCheck::class,
            SetActiveTheme::class,
            SetActiveLanguage::class,
        ]);

        $middleware->alias([
            'api.auth'           => ApiAuth::class,
            'update_pending'     => UpdatePending::class,
            'role'               => RoleMiddleware::class,
            'permission'         => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([
            AbstractHttpException::class,
            IlluminateValidationException::class,
            ModelNotFoundException::class,
            SymfonyHttpException::class,
            TokenMismatchException::class,
        ]);

        //  Custom Rendering Logic
        $exceptions->render(function (Throwable $exception, Request $request) {

            // Handle API Errors
            if ($request->is('api/*')) {
                Log::error('API Error: '.$exception->getMessage(), $exception->getTrace());

                if ($exception instanceof AbstractHttpException) {
                    return $exception->getResponse();
                }

                if ($exception instanceof ModelNotFoundException || $exception instanceof NotFoundHttpException) {
                    return (new AssetNotFound($exception))->getResponse();
                }

                if ($exception instanceof SymfonyHttpException) {
                    return (new SymfonyException($exception))->getResponse();
                }

                if ($exception instanceof IlluminateValidationException) {
                    return (new ValidationException($exception))->getResponse();
                }

                return (new GenericExceptionAbstract($exception))->getResponse();
            }

            // Handle Web Errors (Theme & Auth)
            (new SetActiveTheme())->setTheme($request);

            if ($exception instanceof AbstractHttpException && $exception->getStatusCode() === 403) {
                return redirect()->guest('login');
            }

            // Return null to let the default Laravel handler take over for other cases
            return null;
        });

        // 3. Custom Unauthenticated Logic
        $exceptions->respond(function ($response, Throwable $exception, Request $request) {
            if (!$exception instanceof AuthenticationException) {
                return $response;
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                return (new Unauthenticated())->getResponse();
            }

            return $response;
        });
    })
    ->withEvents([
        __DIR__.'/../app/Cron',
        __DIR__.'/../app/Listeners',
    ])
    ->create();
