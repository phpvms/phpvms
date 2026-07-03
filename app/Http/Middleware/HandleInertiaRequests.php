<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Skylight\Facades\Skylight;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Inertia request handler for the SPA (skylight) theme.
 *
 * Extends the base Inertia middleware to provide the SHARED-PROPS layer every
 * SPA page receives without each controller having to supply it: the
 * authenticated user, app identity, and one-shot flash messages. Page-specific
 * data still comes from each page's Presenter DTO (via response()->themed()).
 *
 * Applied to the frontend route group. On Blade-theme responses (no
 * Inertia::render) the shared props are simply never serialized — harmless.
 */
class HandleInertiaRequests extends Middleware
{
    /**
     * The Inertia root template. A DEDICATED name ('spa') that resolves to the
     * skylight theme's spa.blade.php — deliberately NOT 'app', so it doesn't
     * shadow the Blade `app` layout that non-ported pages (home/login) extend.
     * Those fall back to seven's app layout; only SPA responses use this shell.
     */
    protected $rootView = 'spa';

    /**
     * Asset version — busts the client cache when the skylight build changes so
     * Inertia forces a hard reload on a stale bundle.
     */
    public function version(Request $request): ?string
    {
        $manifest = public_path('build/skylight/manifest.json');

        return is_file($manifest) ? (string) filemtime($manifest) : parent::version($request);
    }

    /**
     * Props shared with every SPA page.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        return [
            ...parent::share($request),

            'appName' => config('app.name'),

            // The skylight extension surface (widget catalog + page slots),
            // accumulated from every ENABLED addon's provider. Serialized here
            // so the SPA builds its catalog + slot registry from the same source
            // of truth. A disabled addon contributes nothing — its provider
            // never booted, so its entries are absent by construction.
            'skylight' => Skylight::toArray(),

            'auth' => [
                'user' => $user ? [
                    'id'     => $user->id,
                    'name'   => $user->name,
                    'avatar' => $user->resolveAvatarUrl(),
                ] : null,
            ],

            // One-shot flash messages, lazily evaluated so they only read the
            // session when a response is actually built.
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
