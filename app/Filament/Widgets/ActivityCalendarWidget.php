<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PirepState;
use App\Filament\Concerns\IsDynamicDashboardWidget;
use App\Models\Pirep;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use MDDev\DynamicDashboard\Contracts\DynamicWidget;
use Override;

/**
 * D3 calendar heatmap of flight activity per hour for the trailing seven
 * days. One row per day, one column per hour.
 *
 * Counts PIREPs, bucketed on ACTIVITY_AT. It used to count `acars` rows by
 * their created_at, which made every box unclickable in practice: the box
 * counted telemetry, while the list it deep-links to filters PIREPs, so the
 * two never agreed and clicking one landed on an empty page.
 */
class ActivityCalendarWidget extends Widget implements DynamicWidget
{
    use IsDynamicDashboardWidget;

    /**
     * When a flight happened: its block-off, or failing that when the report
     * was created. Plenty of PIREPs carry no block_off_time — it is filled by
     * ACARS — and on block_off_time alone those would vanish from the chart.
     *
     * PirepsTable's `departed` filter queries this same expression, so the
     * deep link from a box selects exactly the rows that box counted.
     */
    public const string ACTIVITY_AT = 'COALESCE(block_off_time, created_at)';

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

        // Counts PIREPs, not `acars` rows, and buckets them on the same
        // expression the list's `departed` filter queries — a box and the page
        // it deep-links to have to be counting the same thing, or clicking one
        // lands on an empty list. Same state exclusions as PirepsTable's base
        // query for the same reason.
        Pirep::query()
            ->whereNotIn('state', [PirepState::DRAFT, PirepState::IN_PROGRESS, PirepState::CANCELLED])
            ->whereRaw(self::ACTIVITY_AT.' >= ?', [now()->subDays(6)->startOfDay()])
            ->selectRaw(self::ACTIVITY_AT.' as activity_at')
            ->pluck('activity_at')
            ->each(function (mixed $activityAt) use (&$days): void {
                // selectRaw bypasses the model's casts, so this arrives as a
                // driver-shaped string rather than a Carbon.
                $at = $activityAt instanceof Carbon ? $activityAt : Carbon::parse((string) $activityAt);

                $key = $at->toDateString();
                if (isset($days[$key])) {
                    $days[$key]['values'][$at->hour]++;
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
