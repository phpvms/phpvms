<?php

namespace App\Http\Middleware;

use App\Http\Data\AirlineIdentityData;
use App\Http\Data\PilotChromeData;
use App\Models\User;
use App\Services\MapConfigService;
use App\Services\Theme\ActiveThemeService;
use App\Support\Skylight\Facades\Skylight;
use Igaster\LaravelTheme\Facades\Theme;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Inertia\Middleware;
use Override;

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
    public function __construct(
        private readonly ActiveThemeService $themes,
        private readonly MapConfigService $mapConfig,
    ) {}

    /**
     * The Inertia root template. A dedicated view that points to the Skylight
     * theme's spa.blade.php — deliberately not 'app', so it doesn't
     * shadow the Blade `app` layout that non-ported pages (home/login) extend.
     * Those fall back to seven's app layout; only SPA responses use this shell.
     */
    protected $rootView = 'layouts.skylight.spa';

    /**
     * Asset version — busts the client cache when the skylight build changes so
     * Inertia forces a hard reload on a stale bundle.
     */
    #[Override]
    public function version(Request $request): ?string
    {
        $manifest = public_path('build/skylight/manifest.json');
        $manifestVersion = is_file($manifest) ? (string) filemtime($manifest) : parent::version($request);
        $themeName = Theme::get() ?: 'skylight';
        $themeVersion = $this->themes->revision($themeName)?->revision;

        return hash('sha256', ($manifestVersion ?? '').'|'.($themeVersion ?? ''));
    }

    /**
     * Props shared with every SPA page.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();
        $themeName = Theme::get() ?: 'skylight';

        return [
            ...parent::share($request),

            'appName' => config('app.name'),

            'theme' => $this->themes->revision($themeName)?->document,

            // i18n for the SPA — laravel-vue-i18n consumes this. The current
            // locale (resolved by SetActiveLanguage) plus a flat "group.key" =>
            // value map of the SPA-relevant translation groups, read from the
            // SAME resources/lang/*.php files Blade uses (single source of
            // truth). trans() already falls back to the fallback locale for
            // missing keys, so the map is fully resolved for the active locale.
            'i18n' => [
                'locale'   => app()->getLocale(),
                'messages' => $this->spaMessages(),
            ],

            // The skylight extension surface (widget catalog + page slots),
            // accumulated from every ENABLED addon's provider. Serialized here
            // so the SPA builds its catalog + slot registry from the same source
            // of truth. A disabled addon contributes nothing — its provider
            // never booted, so its entries are absent by construction.
            'skylight' => Skylight::toArray(),

            'auth' => [
                'user' => $user ? [
                    'id'       => $user->id,
                    'name'     => $user->name,
                    'avatar'   => $user->resolveAvatarUrl(),
                    'ident'    => $user->ident,
                    'callsign' => $user->callsign,
                    'airline'  => AirlineIdentityData::fromModel($user->airline)?->toArray(),
                ] : null,
            ],

            'pilotChrome' => fn (): ?PilotChromeData => $user ? PilotChromeData::fromUser($user) : null,

            // Basemap + overlay config for every skylight map surface. One
            // resolver shared with the admin panel's window.filamentData.maps
            // (design.md D7) — lazy because resolving it queries enabled
            // map_layers rows, and not every SPA page renders a map.
            'map' => $this->mapConfig->resolve(...),

            // One-shot flash messages, lazily evaluated so they only read the
            // session when a response is actually built.
            //
            // Two sources: a plain session('success'/'error'), and the
            // laracasts/flash notifier the frontend controllers actually use
            // (Flash::success(), flash()->error()). The notifier writes a
            // collection of Message objects under 'flash_notification'
            // (FlashNotifier::flash()), which nothing here read -- so every
            // Flash::success() in the app was silently invisible to the SPA.
            'flash' => [
                'success' => fn (): ?string => $request->session()->get('success')
                    ?? $this->flashNotification($request, 'success'),
                'error' => fn (): ?string => $request->session()->get('error')
                    ?? $this->flashNotification($request, 'danger'),

                // Passport returns a personal access token's plaintext exactly
                // once, at issue time (ProfileController::store_token flashes
                // it), so the API connections drawer has one chance to show it.
                'plainTextToken' => fn (): ?string => $request->session()->get('plain_text_token'),
            ],
        ];
    }

    /**
     * First laracasts/flash message at the given level, as plain text.
     *
     * Levels are the Bootstrap names the notifier uses: success() writes
     * 'success', error() writes 'danger'.
     */
    private function flashNotification(Request $request, string $level): ?string
    {
        $messages = $request->session()->get('flash_notification');

        if (!$messages instanceof Collection) {
            return null;
        }

        $match = $messages->first(
            fn (mixed $message): bool => is_object($message)
                && ($message->level ?? null) === $level
                && filled($message->message ?? null),
        );

        return $match === null ? null : (string) $match->message;
    }

    /**
     * Translation groups the SPA needs, curated so the shared payload stays
     * small (no installer/filament/email strings the SPA never renders). Add a
     * group here as the SPA grows into it.
     */
    private const array SPA_LANG_GROUPS = [
        'common', 'dashboard', 'flights', 'pireps', 'profile',
        'widgets', 'activities', 'errors', 'validation', 'auth', 'ui',
    ];

    /**
     * Build the flat "group.key" => value message map laravel-vue-i18n expects,
     * for the active locale only.
     *
     * @return array<string, string>
     */
    private function spaMessages(): array
    {
        $messages = [];

        foreach (self::SPA_LANG_GROUPS as $group) {
            $lines = trans($group);

            if (!is_array($lines)) {
                continue; // group file missing → skip
            }

            foreach (Arr::dot($lines) as $key => $value) {
                if (is_string($value)) {
                    $messages[$group.'.'.$key] = $value;
                }
            }
        }

        return $messages;
    }
}
