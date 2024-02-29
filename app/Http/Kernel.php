<?php

namespace App\Http;

use App\Http\Middleware\ApiAuth;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\InstalledCheck;
use App\Http\Middleware\JsonResponse;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\SetActiveLanguage;
use App\Http\Middleware\SetActiveTheme;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\UpdatePending;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

class Kernel extends HttpKernel
{
    protected $middleware = [
        TrustProxies::class,
        CheckForMaintenanceMode::class,
        TrimStrings::class,
        ConvertEmptyStringsToNull::class,
    ];

    protected $middlewareGroups = [
        'api' => [
            // 'throttle:60,1',
            'bindings',
            'json',
        ],
        'web' => [
            InstalledCheck::class,
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            SetActiveTheme::class,
            SetActiveLanguage::class,
        ],
    ];

    protected $middlewareAliases = [
        'api.auth'       => ApiAuth::class,
        'auth'           => Authenticate::class,
        'bindings'       => SubstituteBindings::class,
        'can'            => Authorize::class,
        'guest'          => RedirectIfAuthenticated::class,
        'json'           => JsonResponse::class,
        'theme'          => SetActiveTheme::class,
        'throttle'       => ThrottleRequests::class,
        'update_pending' => UpdatePending::class,
        'verified'       => EnsureEmailIsVerified::class,
        'signed'         => ValidateSignature::class,

        'role'               => RoleMiddleware::class,
        'permission'         => PermissionMiddleware::class,
        'role_or_permission' => RoleOrPermissionMiddleware::class,
    ];
}
