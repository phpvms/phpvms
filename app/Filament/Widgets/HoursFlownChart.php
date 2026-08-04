<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PirepState;
use App\Models\Pirep;
use Filament\Widgets\Widget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Support\Carbon;
use Override;

class HoursFlownChart extends Widget
{
    protected string $view = 'filament.widgets.dashboard.chart';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 3;

    #[Override]
    protected function getViewData(): array
    {
        $data = Trend::query(
            Pirep::query()->whereIn('state', [PirepState::ACCEPTED, PirepState::IN_PROGRESS])
        )
            ->between(start: now()->subDays(13)->startOfDay(), end: now())
            ->perDay()
            ->sum('flight_time');

        return [
            'heading'   => __('filament.dashboard.hours_flown'),
            'chartType' => 'bar',
            'json'      => json_encode([
                'labels' => $data->map(fn (TrendValue $value): string => Carbon::parse($value->date)->format('M j'))->all(),
                'values' => $data->map(fn (TrendValue $value): float => round($value->aggregate / 60, 1))->all(),
            ]),
        ];
    }
}
