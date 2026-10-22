<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\PirepState;
use App\Filament\Concerns\ReadsPageFilters;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Reports\AircraftReport;
use App\Models\Aircraft;
use App\Models\Pirep;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Override;

/**
 * Aircraft utilization — top 15 aircraft by flight time in the selected
 * period. Lives on the Aircraft report page (not the dashboard).
 */
class AircraftUtilizationChart extends Widget
{
    use ReadsPageFilters;

    protected string $view = 'filament.widgets.dashboard.chart';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 9;

    #[Override]
    protected function getViewData(): array
    {
        $start_date = $this->filterStartDate();
        $end_date = $this->filterEndDate();
        $airlines = $this->filterAirlines();

        $aircraft = Pirep::query()
            ->whereNotIn('state', [PirepState::DRAFT, PirepState::IN_PROGRESS, PirepState::CANCELLED])
            ->whereBetween('submitted_at', [$start_date, $end_date])
            ->when(
                filled($airlines),
                fn (Builder $query): Builder => $query->whereIn('airline_id', $airlines),
            )
            ->selectRaw('aircraft_id, SUM(flight_time) as total_minutes')
            ->groupBy('aircraft_id')
            ->orderByDesc('total_minutes')
            ->limit(15)
            ->get();

        // Resolve the aircraft rows in one query (keyed by id) instead of one
        // query per Pirep; skip rows whose aircraft is gone or soft-deleted.
        $aircraftById = Aircraft::query()
            ->whereIn('id', $aircraft->pluck('aircraft_id'))
            ->get()
            ->keyBy('id');

        $rows = $aircraft
            ->filter(fn (Pirep $pirep): bool => $aircraftById->has($pirep->aircraft_id))
            ->values();

        return [
            'heading'   => __('filament.dashboard.aircraft_utilization'),
            'chartType' => 'hbar',
            'json'      => json_encode([
                'labels' => $rows->map(fn (Pirep $pirep): string => $aircraftById[$pirep->aircraft_id]->registration)->all(),
                'values' => $rows->map(fn (Pirep $pirep): int => (int) round(((int) ($pirep->total_minutes ?? 0)) / 60))->all(),
                // Aircraft live under their subfleet (SubfleetResource relation manager).
                'hrefs' => $rows->map(fn (Pirep $pirep): string => route('filament.admin.resources.subfleets.edit', ['record' => $aircraftById[$pirep->aircraft_id]->subfleet_id]))->all(),
            ]),
        ];
    }

    #[Override]
    public static function canView(): bool
    {
        // Only render on the Aircraft report page (or a Livewire update request from it)
        if (request()->url() === AircraftReport::getUrl()) {
            return true;
        }

        return request()->url() !== Dashboard::getUrl() && str(request()->header('referer'))->contains(AircraftReport::getUrl());
    }
}
