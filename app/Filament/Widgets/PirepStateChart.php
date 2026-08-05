<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PirepState;
use App\Models\Pirep;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Override;

class PirepStateChart extends Widget
{
    use InteractsWithPageFilters;

    protected string $view = 'filament.widgets.dashboard.chart';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 5;

    #[Override]
    protected function getViewData(): array
    {
        $filters = $this->pageFilters ?? [
            'start_date' => null,
            'end_date'   => null,
            'airline_id' => null,
        ];

        $query = Pirep::query()
            ->when(
                $filters['start_date'] !== null || $filters['end_date'] !== null,
                fn (Builder $query): Builder => $query->whereBetween(
                    'submitted_at',
                    [
                        $filters['start_date'] !== null ? Carbon::createFromTimeString($filters['start_date']) : now()->startOfYear(),
                        $filters['end_date'] !== null ? Carbon::createFromTimeString($filters['end_date']) : now(),
                    ],
                ),
            )
            ->when(
                filled($filters['airline_id']),
                fn (Builder $query): Builder => $query->where('airline_id', $filters['airline_id']),
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
