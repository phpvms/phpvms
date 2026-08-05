<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PirepState;
use App\Models\Pirep;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Override;

class HoursFlownChart extends Widget
{
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.dashboard.chart';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 3;

    #[Override]
    protected function getViewData(): array
    {
        $filters = $this->pageFilters ?? [
            'start_date' => null,
            'end_date'   => null,
            'airline_id' => null,
        ];

        $start_date = $filters['start_date'] !== null ? Carbon::parse($filters['start_date'])->startOfDay() : now()->subDays(13)->startOfDay();
        $end_date = $filters['end_date'] !== null ? Carbon::parse($filters['end_date'])->endOfDay() : now();
        $airline_id = $filters['airline_id'];

        $data = Trend::query(
            Pirep::query()
                ->whereIn('state', [PirepState::ACCEPTED, PirepState::IN_PROGRESS])
                ->when(
                    filled($airline_id),
                    fn (Builder $query): Builder => $query->where('airline_id', $airline_id),
                )
        )
            ->between(start: $start_date, end: $end_date)
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
