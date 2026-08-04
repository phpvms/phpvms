<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Aircraft;
use Filament\Widgets\Widget;
use Override;

class AircraftUtilizationChart extends Widget
{
    protected string $view = 'filament.widgets.dashboard.chart';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 9;

    #[Override]
    protected function getViewData(): array
    {
        $aircraft = Aircraft::query()
            ->orderByDesc('flight_time')
            ->limit(15)
            ->get(['registration', 'flight_time', 'subfleet_id']);

        return [
            'heading'   => __('filament.dashboard.aircraft_utilization'),
            'chartType' => 'hbar',
            'json'      => json_encode([
                'labels' => $aircraft->pluck('registration')->all(),
                'values' => $aircraft->map(fn (Aircraft $ac): int => (int) round($ac->flight_time / 60))->all(),
                // Aircraft live under their subfleet (SubfleetResource relation manager).
                'hrefs' => $aircraft->map(fn (Aircraft $ac): string => route('filament.admin.resources.subfleets.edit', ['record' => $ac->subfleet_id]))->all(),
            ]),
        ];
    }
}
