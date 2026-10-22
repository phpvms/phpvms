<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Enums\PirepPhase;
use App\Models\Pirep;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Full single-flight map detail (`GET api/map/pirep/{id}`). One DTO serves
 * three consumers: the live map's click-through, the admin PIREP view, and
 * the briefing — for a briefing, `flown.points` is empty and `planned.fixes`
 * is sourced from the live OFP instead of the archive (D10).
 *
 * `flownPoints`, `plannedFixes`, and `events` are supplied by the caller
 * rather than derived here, so the same DTO can be built either from
 * acars/archive (MapController's case) or from a live SimBrief OFP (the
 * briefing's case) without this class depending on either data source.
 */
#[TypeScript]
final class MapPirepDetailData extends Data
{
    /**
     * @param list<MapEventData> $events
     */
    public function __construct(
        public string $pirepId,
        public string $ident,
        public ?string $callsign,
        public ?string $status,
        public ?string $phase,
        public ?MapPilotRefData $pilot,
        public ?AirlineRefData $airline,
        public ?AircraftRefData $aircraft,
        public MapDetailAirportsData $airports,
        public ?string $scheduledArrivalAt,
        public ?string $blockOffTime,
        public ?string $blockOnTime,
        public ?int $plannedFlightTime,
        public ?int $flightTime,
        public ?string $fuelUsed,
        public ?string $blockFuel,
        public ?string $distance,
        public ?string $plannedDistance,
        public ?int $cruiseLevel,
        public ?string $remarks,
        public MapFlownData $flown,
        public MapPlannedRouteData $planned,
        public array $events,
    ) {}

    /**
     * @param list<MapTrackPointData> $flownPoints
     * @param list<MapPlannedFixData> $plannedFixes
     * @param list<MapEventData>      $events
     */
    public static function fromModel(
        Pirep $p,
        array $flownPoints,
        array $plannedFixes,
        array $events,
        ?int $plannedFallbackAltitudeFt = null,
    ): self {
        $phase = self::resolvePhase($p);

        return new self(
            pirepId: $p->id,
            ident: $p->ident,
            callsign: $p->flight?->callsign,
            status: $phase?->getLabel(),
            phase: $phase?->value,
            pilot: $p->user ? new MapPilotRefData(
                id: $p->user->id,
                name: $p->user->name,
                ident: $p->user->ident,
            ) : null,
            airline: $p->airline ? new AirlineRefData(
                icao: $p->airline->icao,
                name: $p->airline->name,
                logo: $p->airline->logo,
            ) : null,
            aircraft: $p->aircraft ? new AircraftRefData(
                id: $p->aircraft->id,
                registration: $p->aircraft->registration,
                name: $p->aircraft->name,
            ) : null,
            airports: new MapDetailAirportsData(
                dpt: self::airportPoint($p->dpt_airport),
                arr: self::airportPoint($p->arr_airport),
                alt: self::airportPoint($p->alt_airport),
            ),
            scheduledArrivalAt: $p->scheduled_arrival_at?->toIso8601String(),
            blockOffTime: $p->block_off_time?->toIso8601String(),
            blockOnTime: $p->block_on_time?->toIso8601String(),
            plannedFlightTime: $p->planned_flight_time,
            flightTime: $p->flight_time,
            fuelUsed: self::fuelLabel($p->fuel_used),
            blockFuel: self::fuelLabel($p->block_fuel),
            distance: PirepListItemData::distanceLabel($p->distance),
            plannedDistance: PirepListItemData::distanceLabel($p->planned_distance),
            // Display metadata only (design.md open question 6) — nothing
            // that positions geometry reads this; the planned-route fallback
            // below is `plannedFallbackAltitudeFt`, derived from altitude_msl.
            cruiseLevel: $p->level,
            remarks: $p->notes,
            flown: new MapFlownData(points: $flownPoints),
            planned: new MapPlannedRouteData(fixes: $plannedFixes, fallbackAltitudeFt: $plannedFallbackAltitudeFt),
            events: $events,
        );
    }

    private static function airportPoint(?object $airport): ?AirportPointData
    {
        return $airport ? new AirportPointData(
            id: $airport->id,
            icao: $airport->icao,
            name: $airport->name,
            lat: $airport->lat !== null ? (float) $airport->lat : null,
            lon: $airport->lon !== null ? (float) $airport->lon : null,
        ) : null;
    }

    /**
     * PirepPhase, tolerant of legacy/invalid stored values. Mirrors
     * PirepData::statusLabel() / MapLiveFlightData::resolvePhase().
     */
    private static function resolvePhase(Pirep $p): ?PirepPhase
    {
        $raw = $p->getRawOriginal('status');

        return is_string($raw) ? PirepPhase::tryFrom($raw) : null;
    }

    /** Unit-aware fuel string in the pilot's configured units, e.g. "4200 lbs". */
    private static function fuelLabel(mixed $fuel): ?string
    {
        if ($fuel === null) {
            return null;
        }

        return round((float) $fuel->local()).' '.setting('units.fuel');
    }
}
