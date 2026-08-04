<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\Widget;
use Override;

class PilotHoursChart extends Widget
{
    protected string $view = 'filament.widgets.dashboard.chart';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 8;

    #[Override]
    protected function getViewData(): array
    {
        $pilots = User::query()
            ->where('flight_time', '>', 0)
            ->orderByDesc('flight_time')
            ->limit(10)
            ->get(['id', 'name', 'flight_time']);

        return [
            'heading'   => __('filament.dashboard.pilot_hours'),
            'chartType' => 'hbar',
            'json'      => json_encode([
                'labels' => $pilots->pluck('name')->all(),
                'values' => $pilots->map(fn (User $user): int => (int) round($user->flight_time / 60))->all(),
                'hrefs'  => $pilots->map(fn (User $user): string => route('filament.admin.resources.users.edit', ['record' => $user->id]))->all(),
            ]),
        ];
    }
}
