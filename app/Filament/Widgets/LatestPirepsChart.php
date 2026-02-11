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

    protected ?string $pollingInterval = null;

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
                    'label' => __('filament.pireps_field'),
                    'data'  => $data->map(fn (TrendValue $value) => $value->aggregate),
                ],
            ],
            'labels' => $data->map(fn (TrendValue $value) => $value->date),
        ];
    }

    protected function getFilters(): ?array
    {
        return [
            'today' => __('filament.today'),
            'week'  => __('filament.this_week'),
            'month' => __('filament.this_month'),
            'year'  => __('filament.this_year'),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    public function getHeading(): string
    {
        return __('filament.pireps_field');
    }
}
