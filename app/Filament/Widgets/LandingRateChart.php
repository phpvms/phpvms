<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

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

class LandingRateChart extends Widget implements DynamicWidget
{
    use IsDynamicDashboardWidget;
    use ReadsPageFilters;

    protected string $view = 'filament.widgets.dashboard.chart';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 6;

    public static function getWidgetLabel(): string
    {
        return __('filament.dashboard.landing_rate');
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
                ->where('landing_rate', '!=', 0)
                ->when(
                    filled($airlines),
                    fn (Builder $query): Builder => $query->whereIn('airline_id', $airlines),
                )
        )
            ->between(start: $start_date, end: $end_date)
            ->perDay()
            ->average('landing_rate');

        return [
            'heading'   => __('filament.dashboard.landing_rate'),
            'chartType' => 'line',
            'json'      => json_encode([
                'labels' => $data->map(fn (TrendValue $value): string => Carbon::parse($value->date)->format('M j'))->all(),
                // Landing rates are negative fpm (descending = touchdown); negate so
                // the line reads "higher = harder landing" instead of upside down.
                'values' => $data->map(fn (TrendValue $value): int => (int) round((float) $value->aggregate * -1))->all(),
            ]),
        ];
    }
}
