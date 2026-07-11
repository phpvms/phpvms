<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Middleware;
use Closure;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Passport\HasApiTokens;

/**
 * Enforce that the authenticated token is allowed to reach a route.
 *
 * Registered as the `scope` (and `scopes`) route middleware alias. Usage:
 *   ->middleware('scope:pireps:write')
 *   ->middleware('scope:flights:read,pireps:read')   // any-of
 *
 * All scope logic is delegated to Passport's own {@see HasApiTokens::tokenCan()}:
 *   - a Passport token passes when it holds the scope (or the `*` wildcard,
 *     which Token::can() handles natively);
 *   - a legacy api_key request passes because {@see ApiAuth} attaches a
 *     TransientToken whose can() returns true for every scope.
 *
 * This thin wrapper exists only to return the project's standard API error
 * shape on failure — Passport's stock middleware throws MissingScopeException,
 * which the app's generic converter would render as a 503 rather than a 403.
 */
class CheckApiScope implements Middleware
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string ...$scopes)
    {
        $user = $request->user();

        foreach ($scopes as $scope) {
            if ($user !== null && $user->tokenCan($scope)) {
                return $next($request);
            }
        }

        return $this->insufficientScope($scopes);
    }

    /**
     * Return a 403 insufficient_scope error in the standard API error shape.
     *
     * @param list<string> $scopes
     */
    private function insufficientScope(array $scopes): ResponseFactory|Response
    {
        return response([
            'error' => [
                'code'      => '403',
                'http_code' => 'Forbidden',
                'message'   => 'insufficient_scope: this token is missing one of the required scopes ('.implode(', ', $scopes).')',
            ],
        ], 403);
    }
}
