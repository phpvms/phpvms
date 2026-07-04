<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Enums\PirepState;
use App\Models\Pirep;
use App\Support\Units\Time;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Flat SPA projection of one PIREP for the logbook list (modeled on the admin
 * PirepResource row card). SPA-only; the Blade path keeps the Eloquent model.
 *
 * Value-object/enum fields are flattened to display-ready scalars (distance +
 * flight time as unit-aware strings, state as label + semantic color token), so
 * the Vue list renders without importing units/enums.
 */
#[TypeScript]
final class PirepListItemData extends Data
{
    public function __construct(
        public string $id,
        public string $ident,
        public ?string $aircraft,
        public string $dpt,
        public string $arr,
        public ?string $dptName,
        public ?string $arrName,
        public ?string $flightTime,
        public ?string $distance,
        public ?int $score,
        public ?float $landingRate,
        public string $state,
        public string $stateColor,
        public ?string $submittedAt,
    ) {}

    public static function fromModel(Pirep $p): self
    {
        return new self(
            id: $p->id,
            ident: $p->ident,
            aircraft: self::aircraftLabel($p),
            dpt: $p->dpt_airport_id,
            arr: $p->arr_airport_id,
            dptName: $p->dpt_airport?->name,
            arrName: $p->arr_airport?->name,
            flightTime: $p->flight_time ? Time::minutesToTimeString((int) $p->flight_time) : null,
            distance: self::distanceLabel($p->distance),
            score: $p->score,
            landingRate: $p->landing_rate,
            state: $p->state?->getLabel() ?? '—',
            stateColor: self::stateColor($p->state),
            submittedAt: $p->submitted_at?->toIso8601String(),
        );
    }

    /** "N123 · A320" from the (eager-loaded) aircraft, defensively. */
    public static function aircraftLabel(Pirep $p): ?string
    {
        $ac = $p->aircraft;
        if ($ac === null) {
            return null;
        }

        $parts = array_filter([$ac->registration, $ac->name]);

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /** Unit-aware distance string in the pilot's configured units, e.g. "512 nmi". */
    public static function distanceLabel(mixed $distance): ?string
    {
        if ($distance === null) {
            return null;
        }

        return round((float) $distance->local()).' '.setting('units.distance');
    }

    /** Map PirepState to a semantic color token the SPA understands. */
    public static function stateColor(?PirepState $state): string
    {
        return match ($state) {
            PirepState::ACCEPTED => 'success',
            PirepState::PENDING  => 'warning',
            PirepState::REJECTED => 'danger',
            PirepState::IN_PROGRESS,
            PirepState::PAUSED, PirepState::DRAFT => 'info',
            default                               => 'gray',
        };
    }
}
