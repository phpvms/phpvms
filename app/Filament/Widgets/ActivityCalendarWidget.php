<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Concerns\IsDynamicDashboardWidget;
use App\Models\Acars;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use MDDev\DynamicDashboard\Contracts\DynamicWidget;
use Override;

/**
 * D3 calendar heatmap of flight activity (ACARS telemetry events) per hour
 * for the trailing seven days. One row per day, one column per hour.
 */
class ActivityCalendarWidget extends Widget implements DynamicWidget
{
    use IsDynamicDashboardWidget;

    protected string $view = 'filament.widgets.dashboard.chart';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public static function getWidgetLabel(): string
    {
        return __('filament.dashboard.activity_calendar');
    }

    public static function getDynamicDashboardDefaultWidth(): int
    {
        return 8;
    }

    public static function getDynamicDashboardDefaultHeight(): int
    {
        return 4;
    }

    public static function getDynamicDashboardMinHeight(): int
    {
        return static::getDynamicDashboardDefaultHeight();
    }

    #[Override]
    protected function getViewData(): array
    {
        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $days[$date->toDateString()] = [
                'date'   => $date->toDateString(),
                'label'  => $date->format('D'),
                'values' => array_fill(0, 24, 0),
            ];
        }

        Acars::query()
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->pluck('created_at')
            ->each(function (Carbon $createdAt) use (&$days): void {
                $key = $createdAt->toDateString();
                if (isset($days[$key])) {
                    $days[$key]['values'][$createdAt->hour]++;
                }
            });

        $max = 0;
        foreach ($days as $day) {
            $max = max($max, max($day['values']));
        }

        return [
            'heading'   => __('filament.dashboard.activity_calendar'),
            'chartType' => 'calendar',
            'json'      => json_encode([
                'days' => array_values($days),
                'max'  => $max,
            ]),
        ];
    }
}
