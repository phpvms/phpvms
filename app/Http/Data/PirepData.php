<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Enums\PirepPhase;
use App\Models\Pirep;
use App\Support\Units\Time;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Flat SPA projection of a single PIREP for the detail view (modeled on the
 * admin ViewPirep header + stat strip + sidebar). SPA-only; the Blade path keeps
 * the Eloquent model (map, comments thread, finances ledger, etc.).
 *
 * Reads only relations the frontend PirepController@show eager-loads
 * (aircraft/airline/airports/fares/acars_logs/user.rank/field_values) — no lazy
 * loads under preventLazyLoading.
 */
#[TypeScript]
final class PirepData extends Data
{
    /**
     * @param list<PirepFieldData> $fields
     * @param list<PirepFareData>  $fares
     * @param list<PirepLogData>   $logs
     */
    public function __construct(
        public string $id,
        public string $ident,
        public ?string $aircraft,
        public ?string $airline,
        public string $dpt,
        public string $arr,
        public ?string $dptName,
        public ?string $arrName,
        public string $state,
        public string $stateColor,
        public ?string $status,
        public string $source,
        public ?string $sourceName,
        public ?string $flightType,
        public ?string $route,
        public ?string $notes,
        public ?string $flightTime,
        public ?string $plannedFlightTime,
        public ?string $distance,
        public ?string $plannedDistance,
        public ?int $score,
        public ?float $landingRate,
        public ?string $fuelUsed,
        public ?string $blockFuel,
        public ?string $cruise,
        public ?string $pilotName,
        public ?string $pilotRank,
        public ?string $submittedAt,
        public array $fields,
        public array $fares,
        public array $logs,
    ) {}

    public static function fromModel(Pirep $p): self
    {
        return new self(
            id: $p->id,
            ident: $p->ident,
            aircraft: PirepListItemData::aircraftLabel($p),
            airline: $p->airline?->name,
            dpt: $p->dpt_airport_id,
            arr: $p->arr_airport_id,
            dptName: $p->dpt_airport?->name,
            arrName: $p->arr_airport?->name,
            state: $p->state->getLabel(),
            stateColor: PirepListItemData::stateColor($p->state),
            status: self::statusLabel($p),
            source: $p->source?->getLabel() ?? '—',
            sourceName: $p->source_name,
            flightType: $p->flight_type->getLabel(),
            route: $p->route,
            notes: $p->notes,
            flightTime: $p->flight_time ? Time::minutesToTimeString((int) $p->flight_time) : null,
            plannedFlightTime: $p->planned_flight_time ? Time::minutesToTimeString((int) $p->planned_flight_time) : null,
            distance: PirepListItemData::distanceLabel($p->distance),
            plannedDistance: PirepListItemData::distanceLabel($p->planned_distance),
            score: $p->score,
            landingRate: $p->landing_rate,
            fuelUsed: self::fuelLabel($p->fuel_used),
            blockFuel: self::fuelLabel($p->block_fuel),
            cruise: $p->level ? 'FL'.$p->level : null,
            pilotName: $p->user?->name,
            pilotRank: $p->user?->rank?->name,
            submittedAt: $p->submitted_at?->toIso8601String(),
            fields: collect($p->fields)
                ->map(fn ($f): PirepFieldData => new PirepFieldData(
                    name: (string) ($f->name ?? ''),
                    value: $f->value !== null ? (string) $f->value : null,
                ))
                ->all(),
            fares: $p->fares
                ->map(fn ($f): PirepFareData => new PirepFareData(
                    name: (string) ($f->name ?? $f->code ?? ''),
                    code: $f->code,
                    count: (int) ($f->count ?? 0),
                ))
                ->all(),
            logs: $p->acars_logs
                ->map(fn ($l): PirepLogData => new PirepLogData(
                    time: $l->created_at?->toIso8601String(),
                    message: (string) ($l->log ?? ''),
                ))
                ->all(),
        );
    }

    /**
     * PirepPhase label, tolerant of legacy/invalid stored values. The status
     * column can hold values outside the string-backed enum (e.g. a stale "0"),
     * which would make the model cast throw on access — so read the raw value and
     * tryFrom() it instead of touching $p->status.
     */
    private static function statusLabel(Pirep $p): ?string
    {
        $raw = $p->getRawOriginal('status');

        return is_string($raw) ? PirepPhase::tryFrom($raw)?->getLabel() : null;
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
