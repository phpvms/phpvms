<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What a flight bundle IS.
 *
 * `Flights` is the ordinary schedule grouping every bundle has always been.
 * `Tour` adds one constraint: its flights carry a contiguous `route_leg`
 * sequence from 1, and a pilot bids the whole chain as a unit.
 *
 * Lives here rather than in the tour slice because it is a column on a core
 * model, not slice-private state.
 */
enum BundleType: string implements HasLabel
{
    case Flights = 'flights';
    case Tour = 'tour';

    public function getLabel(): string
    {
        return match ($this) {
            self::Flights => __('filament.bundles.types.flights'),
            self::Tour    => __('filament.bundles.types.tour'),
        };
    }
}
