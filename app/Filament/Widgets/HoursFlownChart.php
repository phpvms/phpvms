<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PirepState;
use App\Filament\Concerns\IsDynamicDashboardWidget;
use App\Filament\Concerns\ReadsPageFilters;
use App\Models\Pirep;
use Filament\Widgets\Widget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use MDDev\DynamicDashboard\Contracts\DynamicWidget;
use Override;

class HoursFlownChart extends Widget implements DynamicWidget
{
    use IsDynamicDashboardWidget;
    use ReadsPageFilters;

    protected string $view = 'filament.widgets.dashboard.chart';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 3;

    public static function getWidgetLabel(): string
    {
        return __('filament.dashboard.hours_flown');
    }

    public static function getDynamicDashboardDefaultWidth(): int
    {
        return 8;
    }

    public static function getDynamicDashboardDefaultHeight(): int
    {
        return 3;
    }

    public static function getDynamicDashboardMinHeight(): int
    {
        return 3;
    }

    #[Override]
    protected function getViewData(): array
    {
        $start_date = $this->filterStartDate();
        $end_date = $this->filterEndDate();
        $airlines = $this->filterAirlines();

        $data = Trend::query(
            Pirep::query()
                ->whereIn('state', [PirepState::ACCEPTED, PirepState::IN_PROGRESS])
                ->when(
                    filled($airlines),
                    fn (Builder $query): Builder => $query->whereIn('airline_id', $airlines),
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
