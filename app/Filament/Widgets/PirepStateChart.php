<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PirepState;
use App\Models\Pirep;
use Filament\Widgets\Widget;
use Override;

class PirepStateChart extends Widget
{
    protected string $view = 'filament.widgets.dashboard.chart';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 5;

    #[Override]
    protected function getViewData(): array
    {
        $counts = Pirep::query()
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
