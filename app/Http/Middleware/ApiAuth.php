<?php

/**
 * Handle the authentication for the API layer
 */

namespace App\Http\Middleware;

use App\Contracts\Middleware;
use App\Enums\UserState;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\TransientToken;

/**
 * Composite API authentication.
 *
 * A request is authenticated by, in order:
 *   1. Passport — a valid OAuth2 bearer token on the `api` guard. The token's
 *      scopes travel with the resolved user and are enforced downstream by
 *      {@see CheckApiScope}.
 *   2. Legacy fallback — the per-user `api_key` sent raw in `Authorization` or
 *      in `X-API-Key`. Legacy keys are treated as full-access: the user is given
 *      a {@see TransientToken} whose scopes satisfy everything, so Passport's
 *      own `tokenCan()` (used by the scope middleware) lets legacy clients
 *      through every route unchanged.
 *
 * Both paths enforce the same UserState gate (ACTIVE/ON_LEAVE only), force the
 * `en` locale, and return the identical 401 error shape on failure — preserving
 * the behaviour every existing API client depends on.
 */
class ApiAuth implements Middleware
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // 1. Prefer Passport. The token guard reads `Authorization: Bearer <jwt>`
        //    and attaches the resolved access token (with its scopes) to the
        //    user. A legacy key sent raw in Authorization has no `Bearer`
        //    prefix, so bearerToken() is null and the guard returns null —
        //    cleanly falling through to the legacy branch below.
        $user = Auth::guard('api')->user();

        if ($user instanceof User) {
            return $this->authenticate($request, $next, $user);
        }

        // 2. Fall back to the legacy api_key lookup, preserving the historic
        //    header contract (X-API-Key, or the key sent raw in Authorization).
        $api_key = $request->header('x-api-key') ?? $request->header('Authorization');
        if ($api_key === null) {
            return $this->unauthorized('X-API-KEY header missing');
        }

        $user = User::where('api_key', $api_key)->first();
        if ($user === null) {
            return $this->unauthorized('User not found with key "'.$api_key.'"');
        }

        // Grant legacy keys full access via a transient token so every scope
        // check (tokenCan) passes — keeps existing clients working untouched.
        $user->withAccessToken(new TransientToken());

        return $this->authenticate($request, $next, $user);
    }

    /**
     * Apply the shared state gate, bind the user to the request, and continue.
     *
     * @return mixed
     */
    private function authenticate(Request $request, Closure $next, User $user)
    {
        if ($user->state !== UserState::ACTIVE && $user->state !== UserState::ON_LEAVE) {
            return $this->unauthorized('User is not ACTIVE, please contact an administrator');
        }

        // Set the user to the request
        Auth::setUser($user);
        $request->merge(['user' => $user]);
        $request->setUserResolver(fn (): User => $user);

        // Force english locale for API
        app()->setLocale('en');

        return $next($request);
    }

    /**
     * Return an unauthorized message
     */
    private function unauthorized(string $details = ''): ResponseFactory|Response
    {
        return response([
            'error' => [
                'code'      => '401',
                'http_code' => 'Unauthorized',
                'message'   => 'Invalid or missing API key ('.$details.')',
            ],
        ], 401);
    }
}
