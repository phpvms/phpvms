<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PirepState;
use App\Filament\Concerns\ReadsPageFilters;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Reports\PilotsReport;
use App\Models\Pirep;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Override;

/**
 * Pilot hours — top 10 pilots by flight time in the selected period.
 * Lives on the Pilots report page (not the dashboard).
 */
class PilotHoursChart extends Widget
{
    use ReadsPageFilters;

    protected string $view = 'filament.widgets.dashboard.chart';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 8;

    #[Override]
    protected function getViewData(): array
    {
        $start_date = $this->filterStartDate();
        $end_date = $this->filterEndDate();
        $airlines = $this->filterAirlines();

        $pilots = Pirep::query()
            ->whereNotIn('state', [PirepState::DRAFT, PirepState::IN_PROGRESS, PirepState::CANCELLED])
            ->whereBetween('submitted_at', [$start_date, $end_date])
            ->when(
                filled($airlines),
                fn (Builder $query): Builder => $query->whereIn('airline_id', $airlines),
            )
            ->selectRaw('user_id, SUM(flight_time) as total_minutes')
            ->groupBy('user_id')
            ->orderByDesc('total_minutes')
            ->with('user:id,name')
            ->limit(10)
            ->get();

        return [
            'heading'   => __('filament.dashboard.pilot_hours'),
            'chartType' => 'hbar',
            'json'      => json_encode([
                'labels' => $pilots->map(fn (Pirep $pirep): string => $pirep->user->name)->all(),
                'values' => $pilots->map(fn (Pirep $pirep): int => (int) round(((int) ($pirep->total_minutes ?? 0)) / 60))->all(),
                'hrefs'  => $pilots->map(fn (Pirep $pirep): string => route('filament.admin.resources.users.edit', ['record' => $pirep->user_id]))->all(),
            ]),
        ];
    }

    #[Override]
    public static function canView(): bool
    {
        // Only render on the Pilots report page (or a Livewire update request from it)
        if (request()->url() === PilotsReport::getUrl()) {
            return true;
        }

        return request()->url() !== Dashboard::getUrl() && str(request()->header('referer'))->contains(PilotsReport::getUrl());
    }
}
