<?php

declare(strict_types=1);

namespace Modules\PhpvmsDashboard\Livewire;

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
        return view('phpvmsdashboard::livewire.kpi', [
            'value' => (int) (Auth::user()?->flights ?? 0),
            'sub'   => __('phpvmsdashboard::widgets.flights_sub'),
        ]);
    }
}
