<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Controller;
use App\Enums\PirepPhase;
use App\Exceptions\PirepNotFound;
use App\Http\Data\MapEventData;
use App\Http\Data\MapLiveFlightData;
use App\Http\Data\MapPirepDetailData;
use App\Http\Data\MapPlannedFixData;
use App\Http\Data\MapTrackPointData;
use App\Models\Acars;
use App\Models\Pirep;
use App\Models\PirepEvent;
use App\Models\PirepPosition;
use App\Support\MapTrackSimplifier;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;

/**
 * Map data for the shared maplibre package.
 *
 * `live()` is fully public, matching the existing `/api/acars*` posture. The
 * `pirep()` detail is public ONLY for a flight on the live map — any other
 * PIREP (pending, rejected, long since archived) requires an authenticated
 * session, because it carries pilot identity, block times, fuel figures and
 * free-form `remarks` that `/api/acars*` never exposed. This is a corrected
 * decision, not the original one: see design.md D10, "Auth posture —
 * corrected 2026-09-01 after review", and `routes/api.php` for why this
 * route (alone, among map/acars routes) carries the `web` middleware group.
 */
class MapController extends Controller
{
    public function __construct(
        private readonly MapTrackSimplifier $simplifier,
        /**
         * Hard caps so neither endpoint can be made to return an unbounded
         * result set (map-api spec, "Responses are bounded"). Constructor
         * parameters, not class constants, so a test can bind a small cap
         * without creating hundreds of rows to exercise the truncation path
         * — the container supplies these defaults in production. None are
         * expected to bind in normal operation: a VA with 500 simultaneous
         * live flights, or a single flight with 5,000 recorded position
         * pings (many hours at typical ACARS posting intervals), is already
         * an anomaly.
         */
        private readonly int $maxLiveFlights = 500,
        private readonly int $maxTrackPoints = 5000,
        private readonly int $maxEvents = 200,
    ) {}

    /**
     * The live-flights map index: one lean entry per flight on the live map,
     * no track points. `Pirep::scopeOnLiveMap()` inner-joins `pirep_positions`,
     * so every row already has a position — `MapLiveFlightData::fromModel()`
     * still filters defensively rather than trust that join alone. Capped at
     * `$maxLiveFlights`, most-recently-updated first (the scope's own
     * ordering), so a truncation drops the stalest positions first.
     *
     * @return list<MapLiveFlightData>
     */
    public function live(): array
    {
        return Pirep::onLiveMap()->limit($this->maxLiveFlights)->get()
            ->map(static fn (Pirep $p): ?MapLiveFlightData => MapLiveFlightData::fromModel($p))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Full detail for one flight: the complete record, the simplified flown
     * track (from `acars` FLIGHT_PATH rows), the planned route (from the
     * archived navlog), and its classified events.
     *
     * Anonymous callers are refused unless the PIREP is on the live map —
     * see the class docblock. `routes/api.php` puts this action behind the
     * `web` middleware group so `Auth::check()` reflects a logged-in admin
     * or skylight browser session (this route carries no Passport/API-key
     * auth; `web` session is the only credential accepted here).
     *
     * @throws PirepNotFound
     * @throws AuthenticationException
     */
    public function pirep(string $pirep_id): MapPirepDetailData
    {
        $pirep = Pirep::query()
            ->with(['aircraft', 'airline', 'arr_airport', 'dpt_airport', 'alt_airport', 'user', 'user.airline', 'flight', 'metadata'])
            ->find($pirep_id);

        if ($pirep === null) {
            throw new PirepNotFound($pirep_id);
        }

        if (!Auth::check() && !$this->isOnLiveMap($pirep)) {
            throw new AuthenticationException();
        }

        $rawPoints = $this->rawFlownPoints($pirep);

        return MapPirepDetailData::fromModel(
            $pirep,
            flownPoints: $this->simplifiedFlownPoints($rawPoints),
            plannedFixes: $this->plannedFixes($pirep),
            events: $this->events($pirep),
            plannedFallbackAltitudeFt: $this->plannedFallbackAltitudeFt($rawPoints),
        );
    }

    /**
     * "On the live map" is exactly "has a `pirep_positions` row" —
     * `Pirep::scopeOnLiveMap()`'s inner join has no other filter, so this is
     * a cheaper equivalent that doesn't need the join's eager loads.
     */
    private function isOnLiveMap(Pirep $pirep): bool
    {
        return PirepPosition::where('pirep_id', $pirep->id)->exists();
    }

    /**
     * The raw (pre-simplification) flown track, shared by
     * `simplifiedFlownPoints()` and `plannedFallbackAltitudeFt()` so the two
     * don't each query `acars` separately.
     *
     * @return list<array{lat: float, lon: float, altitude: ?float, phase: ?string}>
     */
    private function rawFlownPoints(Pirep $pirep): array
    {
        return Acars::query()
            ->forPirep($pirep->id)
            ->flightPath()
            ->orderedBySimTime()
            ->limit($this->maxTrackPoints)
            ->get()
            ->map(static fn (Acars $point): array => [
                'lat'      => (float) $point->lat,
                'lon'      => (float) $point->lon,
                'altitude' => $point->altitude,
                'phase'    => $point->phase,
            ])
            ->all();
    }

    /**
     * @param  list<array{lat: float, lon: float, altitude: ?float, phase: ?string}> $rawPoints
     * @return list<MapTrackPointData>
     */
    private function simplifiedFlownPoints(array $rawPoints): array
    {
        $simplified = $this->simplifier->simplify($rawPoints);

        return array_map(
            static fn (array $point): MapTrackPointData => new MapTrackPointData(
                lat: $point['lat'],
                lon: $point['lon'],
                altitude: $point['altitude'],
                phase: $point['phase'],
            ),
            $simplified,
        );
    }

    /**
     * What the planned route draws at when a fix carries no per-fix
     * altitude — deliberately derived from real `altitude_msl` telemetry,
     * not `pireps.level` (design.md open question 6: the contract is a
     * flight level, but ~93% of live vmsACARS-sourced rows hold feet with no
     * way to tell which from the column alone).
     *
     * Prefers the median altitude among points the client tagged ENROUTE —
     * median over max/mean so a single misclassified climb/descent point
     * near a phase boundary can't skew it. Falls back to the highest
     * altitude across the whole track when no point is tagged ENROUTE (a
     * short hop, or a client that doesn't tag phase at all) — the peak of a
     * normal climb/cruise/descent profile is a reasonable proxy for cruise
     * absent better information. Null when there's no flown track at all
     * (a briefing, or a prefiled PIREP with no ACARS data yet): there is
     * nothing to derive from, so the planned route draws without a flat
     * fallback rather than inventing an altitude.
     *
     * @param list<array{lat: float, lon: float, altitude: ?float, phase: ?string}> $rawPoints
     */
    private function plannedFallbackAltitudeFt(array $rawPoints): ?int
    {
        $enrouteAltitudes = [];
        $allAltitudes = [];

        foreach ($rawPoints as $point) {
            if ($point['altitude'] === null) {
                continue;
            }

            $allAltitudes[] = $point['altitude'];

            if ($point['phase'] === PirepPhase::ENROUTE->value) {
                $enrouteAltitudes[] = $point['altitude'];
            }
        }

        if ($enrouteAltitudes !== []) {
            return (int) round($this->median($enrouteAltitudes));
        }

        if ($allAltitudes !== []) {
            return (int) round(max($allAltitudes));
        }

        return null;
    }

    /**
     * @param non-empty-list<float> $values
     */
    private function median(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }

        return $values[$middle];
    }

    /**
     * @return list<MapEventData>
     */
    private function events(Pirep $pirep): array
    {
        return PirepEvent::where('pirep_id', $pirep->id)
            ->orderBy('created_at', 'asc')
            ->limit($this->maxEvents)
            ->get()
            ->map(static fn (PirepEvent $event): MapEventData => MapEventData::fromModel($event))
            ->all();
    }

    /**
     * @return list<MapPlannedFixData>
     */
    private function plannedFixes(Pirep $pirep): array
    {
        $navlog = $pirep->metadata->navlog ?? [];

        return array_map(
            static fn (array $fix): MapPlannedFixData => new MapPlannedFixData(
                ident: $fix['ident'] ?? null,
                lat: (float) ($fix['pos_lat'] ?? 0.0),
                lon: (float) ($fix['pos_long'] ?? 0.0),
                altitudeFt: isset($fix['altitude_feet']) ? (int) $fix['altitude_feet'] : null,
                viaAirway: $fix['via_airway'] ?? null,
                isSidStar: (bool) ($fix['is_sid_star'] ?? false),
            ),
            $navlog,
        );
    }
}
