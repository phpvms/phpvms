<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Pirep;
use Filament\Widgets\Widget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Support\Carbon;
use Override;

class LandingRateChart extends Widget
{
    protected string $view = 'filament.widgets.dashboard.chart';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 6;

    #[Override]
    protected function getViewData(): array
    {
        $data = Trend::query(
            Pirep::query()->where('landing_rate', '!=', 0)
        )
            ->between(start: now()->subDays(13)->startOfDay(), end: now())
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
