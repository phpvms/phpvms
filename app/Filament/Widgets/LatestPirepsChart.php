<?php

namespace App\Filament\Widgets;

use App\Models\Pirep;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class LatestPirepsChart extends ChartWidget
{
    use HasWidgetShield;

    protected static ?string $heading = 'Pireps Filed';

    protected static ?string $pollingInterval = null;

    protected static ?int $sort = 2;

    public ?string $filter = 'week';

    protected function getData(): array
    {
        $start_date = match ($this->filter) {
            'today' => now()->startOfDay(),
            'week'  => now()->startOfWeek(),
            'year'  => now()->startOfYear(),
            default => now()->startOfMonth(),
        };

        $data = Trend::model(Pirep::class)
            ->between(
                start: $start_date,
                end: now()
            );

        $data = match ($this->filter) {
            'today' => $data->perHour()->count(),
            'year'  => $data->perMonth()->count(),
            default => $data->perDay()->count(),
        };

        return [
            'datasets' => [
                [
                    'label' => 'Pireps Filed',
                    'data'  => $data->map(fn (TrendValue $value) => $value->aggregate),
                ],
            ],
            'labels' => $data->map(fn (TrendValue $value) => $value->date),
        ];
    }

    protected function getFilters(): ?array
    {
        return [
            'today' => 'Today',
            'week'  => 'This week',
            'month' => 'This month',
            'year'  => 'This year',
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
