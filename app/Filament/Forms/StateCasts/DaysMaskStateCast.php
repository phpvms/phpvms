<?php

declare(strict_types=1);

namespace App\Filament\Forms\StateCasts;

use App\Support\Days;
use Filament\Schemas\Components\StateCasts\Contracts\StateCast;

/**
 * Normalises the `flights.days` bitmask column into the list of Days::*
 * constants a multi-select day picker works in.
 *
 * Both directions produce a list, on purpose. `set()` writes the raw Livewire
 * data that `wire:model` binds the day checkboxes to — handing that the mask
 * instead gives every checkbox one truthy non-array value, which Livewire
 * reads as "all seven selected". Going the other way, the model's days()
 * mutator ORs the dehydrated list back into a mask.
 *
 * Filament's stock OptionsArrayStateCast can't do this: it wraps the raw mask
 * (21 becomes [21]), which matches no option and fails the `in:` rule for
 * every flight that operates on more than one day. Registering this cast via
 * ->stateCast() replaces that default outright.
 */
class DaysMaskStateCast implements StateCast
{
    /**
     * Raw Livewire data -> form state.
     *
     * @return array<int, int>
     */
    public function get(mixed $state): array
    {
        return $this->toDayList($state);
    }

    /**
     * Form state -> raw Livewire data.
     *
     * @return array<int, int>
     */
    public function set(mixed $state): array
    {
        return $this->toDayList($state);
    }

    /**
     * @return array<int, int>
     */
    private function toDayList(mixed $state): array
    {
        if (is_array($state)) {
            return array_values(array_map(intval(...), $state));
        }

        if (blank($state)) {
            return [];
        }

        return array_values(array_filter(
            array_keys(Days::$labels),
            fn (int $day): bool => Days::in((int) $state, $day),
        ));
    }
}
