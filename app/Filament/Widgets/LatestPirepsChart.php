<?php

namespace App\Filament\Widgets;

use App\Models\Pirep;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Override;

class LatestPirepsChart extends ChartWidget
{
    protected ?string $pollingInterval = null;

    protected static ?int $sort = 2;

    public ?string $filter = 'week';

    #[Override]
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
                    'data'  => $data->map(fn (TrendValue $value): mixed => $value->aggregate),
                ],
            ],
            'labels' => $data->map(fn (TrendValue $value): string => $value->date),
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

    #[Override]
    public function getHeading(): string
    {
        return __('filament.pireps_field');
    }
}
