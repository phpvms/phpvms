<?php

declare(strict_types=1);

namespace App\Services\Awards;

use App\Enums\FlightType;
use App\Enums\PirepPhase;
use App\Enums\PirepSource;
use App\Enums\PirepState;
use App\Enums\SimType;
use Filament\QueryBuilder\Constraints\Constraint;
use Filament\QueryBuilder\Constraints\NumberConstraint;
use Filament\QueryBuilder\Constraints\SelectConstraint;
use Filament\QueryBuilder\Constraints\TextConstraint;

/**
 * The `pireps` query-builder vocabulary, generated from the live schema so a
 * newly added column shows up with no code change (design D2).
 *
 * This is the inner vocabulary of the PIREP constraint (design D4), which
 * nests a `RuleBuilder` over it to describe *one* PIREP. It stands alone all
 * the same (design D10): a PIREP report table hands the same array to
 * `QueryBuilder::make()->constraints(...)`.
 *
 * Resolution order per column is: override map wins, then the denylist, then
 * the column type decides the constraint class.
 *
 * Every constraint names a bare `pireps` column, never a dotted one -- see
 * design D3 and the note on `UserConstraints`.
 */
class PirepConstraints
{
    /**
     * Columns that are never useful award criteria: the primary key, opaque
     * record pointers, free-text blobs, and bookkeeping timestamps.
     *
     * Numeric foreign keys are excluded by the `*_id` rule in `make()`
     * rather than listed here. The airport columns are varchar ICAO codes,
     * not numeric keys, so they survive it -- deliberately, since "arrived
     * at KJFK" is one of the most useful criteria there is.
     */
    private const array DENIED = [
        'id',
        'flight_id',
        'route',
        'notes',
        'updated_at',
        'deleted_at',
    ];

    /**
     * @return array<int, Constraint>
     */
    public static function make(): array
    {
        return SchemaConstraints::build('pireps', self::DENIED, self::overrides());
    }

    /**
     * Columns whose generated constraint would be wrong or unreadable: enums
     * that need select options, and abbreviated or unit-bearing names.
     *
     * @return array<string, Constraint>
     */
    private static function overrides(): array
    {
        return [
            'state' => SelectConstraint::make('state')
                ->label('State')
                ->options(PirepState::class)
                ->multiple(),
            'status' => SelectConstraint::make('status')
                ->label('Flight Phase')
                ->options(PirepPhase::class)
                ->multiple(),
            'source' => SelectConstraint::make('source')
                ->label('Source')
                ->options(PirepSource::class)
                ->multiple()
                ->nullable(),
            'sim_type' => SelectConstraint::make('sim_type')
                ->label('Simulator')
                ->options(SimType::class)
                ->multiple()
                ->nullable(),
            'flight_type' => SelectConstraint::make('flight_type')
                ->label('Flight Type')
                ->options(FlightType::class)
                ->multiple(),
            'dpt_airport_id' => TextConstraint::make('dpt_airport_id')
                ->label('Departure Airport'),
            'arr_airport_id' => TextConstraint::make('arr_airport_id')
                ->label('Arrival Airport'),
            'alt_airport_id' => TextConstraint::make('alt_airport_id')
                ->label('Alternate Airport')
                ->nullable(),
            'level' => NumberConstraint::make('level')
                ->label('Flight Level')
                ->integer()
                ->nullable(),
            'flight_time' => NumberConstraint::make('flight_time')
                ->label('Flight Time (minutes)')
                ->integer()
                ->nullable(),
            'planned_flight_time' => NumberConstraint::make('planned_flight_time')
                ->label('Planned Flight Time (minutes)')
                ->integer()
                ->nullable(),
            'zfw' => NumberConstraint::make('zfw')
                ->label('Zero Fuel Weight')
                ->nullable(),
            'landing_rate' => NumberConstraint::make('landing_rate')
                ->label('Landing Rate (fpm)')
                ->nullable(),
            'source_name' => TextConstraint::make('source_name')
                ->label('ACARS Client')
                ->nullable(),
        ];
    }
}
