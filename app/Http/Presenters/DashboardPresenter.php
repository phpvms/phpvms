<?php

namespace App\Http\Presenters;

use App\Enums\UserState;
use App\Models\Airport;
use App\Models\Pirep;
use App\Models\Rank;
use App\Models\User;
use App\Support\Units\Time;
use Illuminate\Support\Facades\Auth;

/**
 * DashboardPresenter — dual-projection presenter for the dashboard page.
 *
 * Gathers data once in the constructor and exposes two projections:
 *   - toBladeArray()   → legacy model-rich shape (Blade / seven theme)
 *   - toInertiaArray() → flat, JSON-serializable DTO (SPA / skylight theme)
 *
 * Decision D2 in openspec/changes/skylight-dashboard-slice/design.md.
 * Decision D5: namespaced in App\Http\Presenters, unversioned.
 */
class DashboardPresenter
{
    /** @var User */
    protected User $user;

    /** @var Pirep|null */
    protected ?Pirep $lastPirep;

    /** @var string|null The ICAO code of the user's current (or home) airport */
    protected ?string $currentAirport;

    /** @var Airport|null The resolved current/home airport model (for coords) */
    protected ?Airport $currentAirportModel;

    /**
     * Construct a presenter for the given authenticated user.
     * All data-gathering happens here so both projections share one path.
     */
    public function __construct(User $user)
    {
        $user->loadMissing(['journal', 'rank']);

        $this->user = $user;

        $this->lastPirep = $user->last_pirep_id
            ? Pirep::with($this->pirepEagerLoads())->find($user->last_pirep_id)
            : null;

        // Prefer current airport; fall back to home airport
        $this->currentAirport = $user->curr_airport_id ?? $user->home_airport_id;

        $this->currentAirportModel = $this->currentAirport
            ? Airport::find($this->currentAirport)
            : null;
    }

    /**
     * Named constructor — resolves from the currently authenticated user.
     */
    public static function fromAuth(): static
    {
        /** @var User $user */
        $user = Auth::user();

        return new static($user);
    }

    /**
     * Named constructor — build from an explicit User instance.
     * Useful for tests that need to inject a specific user.
     */
    public static function from(User $user): static
    {
        return new static($user);
    }

    // -------------------------------------------------------------------------
    // Blade projection (Task 4.1)
    // -------------------------------------------------------------------------

    /**
     * Returns the EXACT shape the seven Blade template expects today.
     * Keys: ['user', 'current_airport', 'last_pirep']
     *
     * @return array{user: User, current_airport: string|null, last_pirep: Pirep|null}
     */
    public function toBladeArray(): array
    {
        return [
            'user'            => $this->user,
            'current_airport' => $this->currentAirport,
            'last_pirep'      => $this->lastPirep,
        ];
    }

    // -------------------------------------------------------------------------
    // Inertia / SPA projection (Task 4.2)
    // -------------------------------------------------------------------------

    /**
     * Returns a flat, JSON-serializable DTO for the SPA (skylight) theme.
     * NO Eloquent model instances — only scalars, arrays, and nulls.
     *
     * Fields:
     *   - id              string   NanoID of the user
     *   - name            string   Display name
     *   - flights         int      Total accepted flights
     *   - flightTimeMinutes string Formatted as "Xh Ym" (same as @minutestotime blade directive)
     *   - onLeave         bool     True when user state === UserState::ON_LEAVE
     *   - balance         mixed    Formatted money string or null (from optional(journal)->balance)
     *   - currentAirport  string|null  ICAO of current or home airport
     *   - lastPirep       array|null   Flat array of last PIREP with nested relations, or null
     */
    public function toInertiaArray(): array
    {
        return [
            'id'               => $this->user->id,
            'name'             => $this->user->name,
            'flights'          => (int) $this->user->flights,
            'flightTimeMinutes' => Time::minutesToTimeString((int) ($this->user->flight_time ?? 0)),
            'onLeave'          => $this->user->state === UserState::ON_LEAVE,
            'balance'          => $this->serializeBalance(),
            'currentAirport'  => $this->currentAirport,
            'lastPirep'        => $this->serializeLastPirep(),
            'rank'             => $this->serializeRank(),
            'route'            => $this->serializeRoute(),
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Returns the eager-load map for the last PIREP query.
     * Mirrors the original DashboardController's $with_pirep exactly.
     * Defined as a method (not a static property) because PHP disallows
     * closures in class-level constant expressions.
     */
    protected function pirepEagerLoads(): array
    {
        return [
            'aircraft'    => fn ($query) => $query->withTrashed(),
            'arr_airport' => fn ($query) => $query->withTrashed(),
            'comments',
            'dpt_airport' => fn ($query) => $query->withTrashed(),
        ];
    }

    // -------------------------------------------------------------------------
    // Serialization helpers
    // -------------------------------------------------------------------------

    /**
     * Serialize the journal balance for JSON output.
     *
     * The Blade template renders: optional($user->journal)->balance ?? 0
     * which — after MoneyCast — is an App\Support\Money object whose __toString()
     * returns the formatted currency string (e.g. "$1,234.56").
     *
     * For the SPA we expose BOTH the raw numeric amount and the formatted string
     * so the Vue page can choose how to display it.
     *
     * Returns null when there is no journal (new user with no transactions).
     *
     * @return array{amount: float|int, formatted: string}|null
     */
    protected function serializeBalance(): ?array
    {
        $journal = $this->user->journal;

        if ($journal === null) {
            return null;
        }

        /** @var \App\Support\Money|null $money */
        $money = $journal->balance;

        if ($money === null) {
            return null;
        }

        return [
            'amount'    => $money->getValue(),
            'formatted' => (string) $money,
        ];
    }

    /**
     * Serialize the last PIREP to a plain array with the nested relations
     * the Blade template and pirep_card partial access:
     *   - id, ident, state (int + label), flight_number, airline_id
     *   - dpt_airport: {id, icao, name}
     *   - arr_airport: {id, icao, name}
     *   - aircraft:    {id, registration, name}|null  (may be soft-deleted)
     *   - comments:    [{id, comment, created_at}]
     *
     * Returns null when the user has no last PIREP.
     *
     * @return array<string, mixed>|null
     */
    protected function serializeLastPirep(): ?array
    {
        $pirep = $this->lastPirep;

        if ($pirep === null) {
            return null;
        }

        return [
            'id'          => $pirep->id,
            'ident'       => $pirep->ident,
            'flight_number' => $pirep->flight_number,
            'airline_id'  => $pirep->airline_id,
            'state'       => [
                'value' => $pirep->state->value,
                'label' => $pirep->state->getLabel(),
                'color' => $pirep->state->getColor(),
            ],
            'flight_time' => $pirep->flight_time,
            'submitted_at' => $pirep->submitted_at?->toIso8601String(),
            'created_at'  => $pirep->created_at?->toIso8601String(),
            'dpt_airport' => $pirep->dpt_airport ? [
                'id'   => $pirep->dpt_airport->id,
                'icao' => $pirep->dpt_airport->icao,
                'name' => $pirep->dpt_airport->name,
                'lat'  => $pirep->dpt_airport->lat,
                'lon'  => $pirep->dpt_airport->lon,
            ] : null,
            'arr_airport' => $pirep->arr_airport ? [
                'id'   => $pirep->arr_airport->id,
                'icao' => $pirep->arr_airport->icao,
                'name' => $pirep->arr_airport->name,
                'lat'  => $pirep->arr_airport->lat,
                'lon'  => $pirep->arr_airport->lon,
            ] : null,
            'aircraft' => $pirep->aircraft ? [
                'id'           => $pirep->aircraft->id,
                'registration' => $pirep->aircraft->registration,
                'name'         => $pirep->aircraft->name,
            ] : null,
            'comments' => $pirep->comments->map(fn ($c) => [
                'id'         => $c->id,
                'comment'    => $c->comment,
                'created_at' => $c->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * Serialize rank progress for the RankTape widget.
     *
     * Percent = progress in flight hours from the current rank's threshold to
     * the next rank's threshold. Returns null when the user has no rank.
     *
     * @return array{from: string, to: string|null, pct: int}|null
     */
    protected function serializeRank(): ?array
    {
        /** @var Rank|null $rank */
        $rank = $this->user->rank;

        if ($rank === null) {
            return null;
        }

        $userHours = ((int) ($this->user->flight_time ?? 0)) / 60.0;
        $fromHours = (float) $rank->hours;

        /** @var Rank|null $next */
        $next = Rank::where('hours', '>', $rank->hours)
            ->orderBy('hours')
            ->first();

        if ($next === null) {
            return ['from' => $rank->name, 'to' => null, 'pct' => 100];
        }

        $span = max((float) $next->hours - $fromHours, 0.0001);
        $pct = (int) round(max(0.0, min(1.0, ($userHours - $fromHours) / $span)) * 100);

        return ['from' => $rank->name, 'to' => $next->name, 'pct' => $pct];
    }

    /**
     * Serialize the Nav Display route (origin → destination with coords).
     *
     * Prefers the last flown PIREP (dpt → arr); falls back to the current
     * airport as an origin-only marker. Coordinates are [lon, lat] pairs
     * (GeoJSON order) or null when unavailable.
     *
     * @return array{from: array|null, to: array|null}
     */
    protected function serializeRoute(): array
    {
        $point = static function (?string $icao, ?string $name, $lat, $lon): ?array {
            if ($icao === null || $lat === null || $lon === null) {
                return null;
            }

            return [
                'icao' => $icao,
                'name' => $name,
                'lat'  => (float) $lat,
                'lon'  => (float) $lon,
            ];
        };

        // Prefer the last flown route.
        if ($this->lastPirep && $this->lastPirep->dpt_airport && $this->lastPirep->arr_airport) {
            $dpt = $this->lastPirep->dpt_airport;
            $arr = $this->lastPirep->arr_airport;

            return [
                'from' => $point($dpt->icao, $dpt->name, $dpt->lat, $dpt->lon),
                'to'   => $point($arr->icao, $arr->name, $arr->lat, $arr->lon),
            ];
        }

        // Fall back to the current airport (origin-only).
        $curr = $this->currentAirportModel;

        return [
            'from' => $curr ? $point($curr->icao, $curr->name, $curr->lat, $curr->lon) : null,
            'to'   => null,
        ];
    }
}
