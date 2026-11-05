<?php

declare(strict_types=1);

namespace App\Features\Shell;

use App\Features\Bids\Queries\PilotBids;
use App\Http\Data\AirlineIdentityData;
use App\Http\Data\MapConfigData;
use App\Http\Data\PilotChromeData;
use App\Models\Pirep;
use App\Models\User;
use App\Services\MapConfigService;
use App\Services\NavigationService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Shell data for Blade pilot themes: the same values skylight receives as
 * Inertia shared props (`HandleInertiaRequests::share()`), read from the same
 * sources.
 *
 * Each part is computed on first use and memoised, so a layout that never
 * renders a map never resolves the map config. Build one per request.
 */
final class ShellData
{
    /** @var array<string, mixed> */
    private array $memo = [];

    public function __construct(
        private readonly Request $request,
        private readonly NavigationService $navigation,
        private readonly MapConfigService $mapConfig,
    ) {}

    public function appName(): string
    {
        return (string) config('app.name');
    }

    /**
     * @return array{id: int, name: string, avatar: ?string, ident: string, callsign: ?string, airline: ?array<string, mixed>}|null
     */
    public function user(): ?array
    {
        return $this->remember('user', function (): ?array {
            $user = $this->pilot();

            return $user instanceof User ? [
                'id'       => $user->id,
                'name'     => $user->name,
                'avatar'   => $user->resolveAvatarUrl(),
                'ident'    => $user->ident,
                'callsign' => $user->callsign,
                'airline'  => AirlineIdentityData::fromModel($user->airline)?->toArray(),
            ] : null;
        });
    }

    /**
     * Whether the viewer may open the admin panel (the same rule the panel uses).
     */
    public function canAccessAdmin(): bool
    {
        return $this->remember('canAccessAdmin', fn (): bool => $this->pilot()?->hasAdminAccess() ?? false);
    }

    public function pilotChrome(): ?PilotChromeData
    {
        return $this->remember('pilotChrome', fn (): ?PilotChromeData => ($user = $this->pilot()) instanceof User ? PilotChromeData::fromUser($user) : null);
    }

    /**
     * The pilot's latest flight (the `last_pirep` the dashboard uses), for the
     * header: its ident, route and PIREP page. Null when there is none.
     *
     * @return array{ident: string, route: string, url: string}|null
     */
    public function previousFlight(): ?array
    {
        return $this->remember('previousFlight', function (): ?array {
            $id = $this->pilot()?->last_pirep_id;
            $pirep = $id === null ? null : Pirep::query()->with('airline')->find($id);

            if (!$pirep instanceof Pirep || blank($pirep->dpt_airport_id) || blank($pirep->arr_airport_id)) {
                return null;
            }

            return [
                'ident' => $pirep->ident,
                'route' => $pirep->dpt_airport_id.' → '.$pirep->arr_airport_id,
                'url'   => route('frontend.pireps.show', ['id' => $pirep->id]),
            ];
        });
    }

    /**
     * The pilot's next flight: the first bid, as on My bids and the Next flight
     * widget, with its tour when the bid is a tour leg. Null when there is no bid.
     *
     * @return array{ident: string, route: string, url: string, tour: ?string, tourUrl: ?string}|null
     */
    public function nextFlight(): ?array
    {
        return $this->remember('nextFlight', function (): ?array {
            $user = $this->pilot();
            $bid = $user instanceof User ? app(PilotBids::class)->for($user)[0] ?? null : null;

            if ($bid === null || $bid->flight === null) {
                return null;
            }

            return [
                'ident'   => $bid->flight->callsign,
                'route'   => ($bid->flight->dpt ?? '—').' → '.($bid->flight->arr ?? '—'),
                'url'     => route('frontend.flights.bids'),
                'tour'    => $bid->tourName,
                'tourUrl' => $bid->tourId === null ? null : route('frontend.tours.show', ['id' => $bid->tourId]),
            ];
        });
    }

    /**
     * The site menu, ordered, nested and filtered for this viewer.
     *
     * @return array<int, array<string, mixed>>
     */
    public function navigation(): array
    {
        return $this->remember('navigation', fn (): array => $this->navigation->menu($this->pilot() instanceof User));
    }

    public function map(): MapConfigData
    {
        return $this->remember('map', fn (): MapConfigData => $this->mapConfig->resolve());
    }

    /**
     * One-shot flash messages, from a plain session('success'/'error') or the
     * laracasts/flash notifier, plus a newly issued personal access token.
     *
     * @return array{success: ?string, error: ?string, plainTextToken: ?string}
     */
    public function flash(): array
    {
        // An error page can render before the session middleware ran (a 404 from outside the web group).
        if (!$this->request->hasSession()) {
            return ['success' => null, 'error' => null, 'plainTextToken' => null];
        }

        return $this->remember('flash', fn (): array => [
            'success'        => $this->request->session()->get('success') ?? $this->flashNotification('success'),
            'error'          => $this->request->session()->get('error') ?? $this->flashNotification('danger'),
            'plainTextToken' => $this->request->session()->get('plain_text_token'),
        ]);
    }

    private function pilot(): ?User
    {
        $user = $this->request->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * First laracasts/flash message at the given level ('success' or 'danger').
     */
    private function flashNotification(string $level): ?string
    {
        $messages = $this->request->session()->get('flash_notification');

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
     * @template T
     *
     * @param  callable(): T $resolve
     * @return T
     */
    private function remember(string $key, callable $resolve): mixed
    {
        if (!array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $resolve();
        }

        return $this->memo[$key];
    }
}
