<?php

declare(strict_types=1);

namespace App\Services\Awards;

use App\Enums\UserState;
use Filament\QueryBuilder\Constraints\BooleanConstraint;
use Filament\QueryBuilder\Constraints\Constraint;
use Filament\QueryBuilder\Constraints\DateConstraint;
use Filament\QueryBuilder\Constraints\NumberConstraint;
use Filament\QueryBuilder\Constraints\SelectConstraint;
use Filament\QueryBuilder\Constraints\TextConstraint;

/**
 * The `users` query-builder vocabulary, generated from the live schema so a
 * newly added column shows up with no code change (design D2).
 *
 * Standalone on purpose (design D10): awards embed this in a `RuleBuilder`
 * field, and a report table hands the same array to
 * `QueryBuilder::make()->constraints(...)`. Nothing here knows about awards.
 *
 * Resolution order per column is: override map wins, then the denylist, then
 * the column type decides the constraint class.
 *
 * Every constraint names a bare `users` column, never a dotted one. Filament
 * applies each rule as its own `whereHas`, so two dotted rules about "a
 * PIREP" can silently match two different PIREPs (design D3). PIREP criteria
 * go through the dedicated PIREP constraint instead.
 */
class UserConstraints
{
    /**
     * Columns that are never useful award criteria: the primary key,
     * credentials and tokens, bookkeeping timestamps, free-text blobs,
     * opaque record pointers, and third-party integration identifiers.
     *
     * Numeric foreign keys are excluded by the `*_id` rule in `make()`
     * rather than listed here. Varchar ICAO columns such as
     * `home_airport_id` survive it, which is the intent.
     */
    private const array DENIED = [
        'id',
        'password',
        'api_key',
        'remember_token',
        'updated_at',
        'deleted_at',
        'notes',
        'last_pirep_id',
        'avatar',
        'timezone',
        'last_ip',
        'simbrief_username',
        'discord_id',
        'discord_private_channel_id',
        'vatsim_id',
        'ivao_id',
        'status',
    ];

    /**
     * @return array<int, Constraint>
     */
    public static function make(): array
    {
        return SchemaConstraints::build('users', self::DENIED, self::overrides());
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
            'pilot_id' => NumberConstraint::make('pilot_id')
                ->label('Pilot ID')
                ->integer()
                ->nullable(),
            'state' => SelectConstraint::make('state')
                ->label('State')
                ->options(UserState::class)
                ->multiple()
                ->nullable(),
            'home_airport_id' => TextConstraint::make('home_airport_id')
                ->label('Home Airport')
                ->nullable(),
            'curr_airport_id' => TextConstraint::make('curr_airport_id')
                ->label('Current Airport')
                ->nullable(),
            'flight_time' => NumberConstraint::make('flight_time')
                ->label('Flight Time (minutes)')
                ->integer()
                ->nullable(),
            'transfer_time' => NumberConstraint::make('transfer_time')
                ->label('Transfer Time (minutes)')
                ->integer()
                ->nullable(),
            'toc_accepted' => BooleanConstraint::make('toc_accepted')
                ->label('Terms Accepted')
                ->nullable(),
            'opt_in' => BooleanConstraint::make('opt_in')
                ->label('Opted In To Email')
                ->nullable(),
            'lastlogin_at' => DateConstraint::make('lastlogin_at')
                ->label('Last Login')
                ->nullable(),
        ];
    }
}
