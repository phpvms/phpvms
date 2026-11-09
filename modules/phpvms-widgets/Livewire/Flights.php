<?php

declare(strict_types=1);

namespace Modules\PhpvmsWidgets\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Flights KPI: the pilot's accepted flight count.
 */
class Flights extends Component
{
    public function render(): View
    {
        return view('phpvmswidgets::livewire.kpi', [
            'value' => (int) (Auth::user()?->flights ?? 0),
            'sub'   => __('phpvmswidgets::widgets.flights_sub'),
        ]);
    }
}
