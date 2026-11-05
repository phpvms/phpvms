<?php

declare(strict_types=1);

namespace Modules\PhpvmsWidgets\Livewire;

use App\Models\Pirep;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Last flight: the pilot's most recent PIREP, its route, block time and aircraft.
 */
class LastFlight extends Component
{
    public function render(): View
    {
        $id = Auth::user()?->last_pirep_id;

        $pirep = $id === null ? null : Pirep::query()
            ->with([
                'aircraft'    => fn ($query) => $query->withTrashed(),
                'arr_airport' => fn ($query) => $query->withTrashed(),
                'dpt_airport' => fn ($query) => $query->withTrashed(),
            ])
            ->find($id);

        return view('phpvmswidgets::livewire.last-flight', [
            'pirep' => $pirep,
            // HH:MM, the same as the skylight widget, so both themes match.
            'block' => $pirep?->flight_time === null ? '—' : sprintf('%02d:%02d', intdiv((int) $pirep->flight_time, 60), (int) $pirep->flight_time % 60),
        ]);
    }
}
