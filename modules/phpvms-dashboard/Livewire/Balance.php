<?php

declare(strict_types=1);

namespace Modules\PhpvmsDashboard\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Balance KPI: the pilot's journal balance, formatted by its Money value.
 */
class Balance extends Component
{
    public function render(): View
    {
        $balance = Auth::user()?->journal?->balance;

        return view('phpvmsdashboard::livewire.kpi', [
            'value' => $balance === null ? '—' : (string) $balance,
            'sub'   => __('phpvmsdashboard::widgets.balance_sub'),
        ]);
    }
}
