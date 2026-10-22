<?php

declare(strict_types=1);

namespace App\Services\Awards;

use App\Enums\BundleType;
use App\Features\Tour\Enums\TourStatus;
use App\Models\FlightBundle;
use Filament\QueryBuilder\Constraints\Constraint;
use Filament\QueryBuilder\Constraints\NumberConstraint;
use Filament\QueryBuilder\Constraints\SelectConstraint;

/**
 * The `user_tours` query-builder vocabulary, generated from the live schema
 * so a newly added column shows up with no code change (design D2).
 *
 * This is the inner vocabulary of the tour constraint (design D-tour-2),
 * which nests a `RuleBuilder` over it to describe *one* tour run. It stands
 * alone all the same (design D10): a tour report table hands the same array
 * to `QueryBuilder::make()->constraints(...)`.
 *
 * Resolution order per column is: override map wins, then the denylist, then
 * the column type decides the constraint class.
 *
 * Every constraint names a bare `user_tours` column, never a dotted one --
 * see design D3 and the note on `PirepConstraints`.
 */
class TourConstraints
{
    /**
     * Columns that are never useful award criteria: the primary key, opaque
     * record pointers, free-text blobs, and bookkeeping timestamps.
     *
     * `user_id` and `aircraft_id` are numeric foreign keys excluded by the
     * `*_id` rule in `SchemaConstraints::fromColumn()`, same as `bundle_id` --
     * `bundle_id` gets an override below so "completed *this* tour" stays
     * available despite that rule. `pirep_id` and `flight_id` are varchar
     * nanoids, not numeric keys, so the `*_id` rule does not exclude them --
     * they are opaque record pointers denied explicitly, the way
     * `PirepConstraints` denies `flight_id`.
     */
    private const array DENIED = [
        'id',
        'pirep_id',
        'flight_id',
        'description',
        'legs',
        'updated_at',
    ];

    /**
     * @return array<int, Constraint>
     */
    public static function make(): array
    {
        return SchemaConstraints::build('user_tours', self::DENIED, self::overrides());
    }

    /**
     * Columns whose generated constraint would be wrong or unreadable: enums
     * that need select options, the tour bundle picker, and unit-bearing
     * names.
     *
     * @return array<string, Constraint>
     */
    private static function overrides(): array
    {
        return [
            // Kept in the vocabulary even though `TourOperator` forces
            // `status = Completed` on every subquery -- mirrors
            // `PirepConstraints`, which keeps `state` even though
            // `PirepOperator` forces ACCEPTED (design D10: the vocabulary
            // stands alone for report tables).
            'status' => SelectConstraint::make('status')
                ->label('Status')
                ->options(TourStatus::class)
                ->multiple(),

            // The single most important criterion an award can name --
            // "completed *this* tour" -- so it exists despite the `*_id`
            // numeric-FK rule excluding it by default.
            'bundle_id' => SelectConstraint::make('bundle_id')
                ->label('Tour')
                ->searchable()
                ->native(false)
                ->getSearchResultsUsing(fn (string $search): array => FlightBundle::query()
                    ->where('type', BundleType::Tour)
                    ->whereLike('name', "%{$search}%", caseSensitive: false)
                    ->orderBy('name')
                    ->limit(50)
                    ->get()
                    ->mapWithKeys(fn (FlightBundle $bundle): array => [$bundle->getKey() => $bundle->name])
                    ->all())
                ->getOptionLabelUsing(function (mixed $value): ?string {
                    if (!is_numeric($value)) {
                        return null;
                    }

                    $bundle = FlightBundle::find($value);

                    // Falls back to the raw stored value rather than null when
                    // the bundle is gone. Returning null makes the select
                    // discard the value during hydration, which silently
                    // drops the criterion -- and a dropped criterion grants
                    // the award to everyone.
                    return $bundle instanceof FlightBundle ? $bundle->name : (string) $value;
                }),

            'legs_total' => NumberConstraint::make('legs_total')
                ->label('Legs In Tour')
                ->integer(),

            'legs_completed' => NumberConstraint::make('legs_completed')
                ->label('Legs Completed')
                ->integer(),
        ];
    }
}
