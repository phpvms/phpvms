<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PirepState;
use App\Filament\Concerns\IsDynamicDashboardWidget;
use App\Models\Pirep;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use MDDev\DynamicDashboard\Contracts\DynamicWidget;
use Override;

class PirepStateChart extends Widget implements DynamicWidget
{
    use InteractsWithPageFilters;
    use IsDynamicDashboardWidget;

    protected string $view = 'filament.widgets.dashboard.chart';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 5;

    public static function getWidgetLabel(): string
    {
        return __('filament.dashboard.pireps_by_state');
    }

    public static function getDynamicDashboardDefaultWidth(): int
    {
        return 4;
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
        $filters = array_replace([
            'start_date' => null,
            'end_date'   => null,
            'airlines'   => [],
        ], $this->pageFilters ?? []);

        $query = Pirep::query()
            ->when(
                $filters['start_date'] !== null || $filters['end_date'] !== null,
                fn (Builder $query): Builder => $query->whereBetween(
                    'submitted_at',
                    [
                        $filters['start_date'] !== null ? Carbon::parse($filters['start_date'])->startOfDay() : now()->startOfYear(),
                        $filters['end_date'] !== null ? Carbon::parse($filters['end_date'])->endOfDay() : now(),
                    ],
                ),
            )
            ->when(
                filled($filters['airlines']),
                fn (Builder $query): Builder => $query->whereIn('airline_id', $filters['airlines']),
            );

        $counts = $query
            ->pluck('state')
            ->map(fn (PirepState $state): int => $state->value)
            ->countBy();

        $labels = [];
        $values = [];
        foreach (PirepState::cases() as $state) {
            if ($state === PirepState::DELETED) {
                continue;
            }

            $labels[] = $state->getLabel();
            $values[] = $counts->get($state->value, 0);
        }

        return [
            'heading'   => __('filament.dashboard.pireps_by_state'),
            'chartType' => 'doughnut',
            'json'      => json_encode([
                'labels' => $labels,
                'values' => $values,
            ]),
        ];
    }
}
