<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Enums\PirepPhase;
use App\Models\Pirep;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One entry in the live-flights map index (`GET api/map/live`). Deliberately
 * lean: identity, human-readable labels, and one position — no track points.
 * The client derives its marker source from a list of these; positions are
 * not duplicated as a GeoJSON FeatureCollection alongside them (D10).
 *
 * Reads only relations `Pirep::scopeOnLiveMap()` eager-loads (aircraft,
 * airline, arr_airport, dpt_airport, position, user, user.airline) — no lazy
 * loads under preventLazyLoading.
 */
#[TypeScript]
final class MapLiveFlightData extends Data
{
    public function __construct(
        public string $pirepId,
        public string $ident,
        public ?string $status,
        public ?string $phase,
        public float $progress,
        public ?MapPilotRefData $pilot,
        public ?AirlineRefData $airline,
        public ?AircraftRefData $aircraft,
        public ?AirportRefData $dptAirport,
        public ?AirportRefData $arrAirport,
        public MapPositionData $position,
    ) {}

    /**
     * Null when the pirep has no position row, so the caller can filter it
     * out of the index rather than emit null coordinates.
     */
    public static function fromModel(Pirep $p): ?self
    {
        $position = $p->position;
        if ($position === null) {
            return null;
        }

        $phase = self::resolvePhase($p);

        return new self(
            pirepId: $p->id,
            ident: $p->ident,
            status: $phase?->getLabel(),
            phase: $phase?->value,
            progress: (float) $p->progress_percent,
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
            dptAirport: AirportRefData::fromModel($p->dpt_airport),
            arrAirport: AirportRefData::fromModel($p->arr_airport),
            position: new MapPositionData(
                lat: (float) $position->lat,
                lon: (float) $position->lon,
                altitude: (float) $position->altitude,
                heading: (float) $position->heading,
            ),
        );
    }

    /**
     * PirepPhase, tolerant of legacy/invalid stored values. The status column
     * can hold values outside the string-backed enum, which would make the
     * model cast throw on access — so read the raw value and tryFrom() it
     * instead of touching $p->status. Mirrors PirepData::statusLabel().
     */
    private static function resolvePhase(Pirep $p): ?PirepPhase
    {
        $raw = $p->getRawOriginal('status');

        return is_string($raw) ? PirepPhase::tryFrom($raw) : null;
    }
}
