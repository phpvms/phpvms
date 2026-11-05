<?php

declare(strict_types=1);

namespace Modules\PhpvmsDashboard\Livewire;

use App\Support\Units\Time;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Total-hours KPI: the pilot's logged flight time.
 */
class TotalHours extends Component
{
    public function render(): View
    {
        return view('phpvmsdashboard::livewire.kpi', [
            'value' => Time::minutesToTimeString((int) (Auth::user()?->flight_time ?? 0)),
            'sub'   => __('phpvmsdashboard::widgets.hours_sub'),
        ]);
    }
}
