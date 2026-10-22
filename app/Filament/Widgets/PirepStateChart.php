<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PirepState;
use App\Filament\Concerns\IsDynamicDashboardWidget;
use App\Filament\Concerns\ReadsPageFilters;
use App\Models\Pirep;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use MDDev\DynamicDashboard\Contracts\DynamicWidget;
use Override;

class PirepStateChart extends Widget implements DynamicWidget
{
    use IsDynamicDashboardWidget;
    use ReadsPageFilters;

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
        $airlines = $this->filterAirlines();

        // Bucketed on when the flight happened, not when it was filed. A
        // chart of *every* state has to place the states that never reach
        // filing — in progress, draft, cancelled carry no `submitted_at` — or
        // those slices are structurally always zero.
        $query = Pirep::query()
            ->whereRaw(Pirep::ACTIVITY_AT.' BETWEEN ? AND ?', [
                $this->filterStartDate(),
                $this->filterEndDate(),
            ])
            ->when(
                filled($airlines),
                fn (Builder $query): Builder => $query->whereIn('airline_id', $airlines),
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
