<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Reports\AircraftReport;
use App\Models\Subfleet;
use App\Services\ExportService;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Csv\CannotInsertRecord;
use Override;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Fleet utilization report — active aircraft count, total and average
 * flight hours per subfleet, with a CSV export of the same rows.
 * Honors the Reports page airline filter (period filters don't apply:
 * aircraft flight hours are cumulative, not per-period).
 */
class FleetUtilizationTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected static ?string $pollingInterval = null;

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->query($this->baseQuery())
            ->heading(__('filament.reports_fleet_heading'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('common.subfleet'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('type')
                    ->label(__('common.type'))
                    ->sortable(),

                TextColumn::make('aircraft_count')
                    ->label(__('common.aircraft'))
                    ->numeric()
                    ->sortable(),

                TextColumn::make('aircraft_sum_flight_time')
                    ->label(__('filament.reports_fleet_total_hours'))
                    ->formatStateUsing(fn (int|string|null $state): string => number_format((int) $state / 60, 1).' h')
                    ->sortable(),

                TextColumn::make('avg_hours_per_aircraft')
                    ->label(__('filament.reports_fleet_avg_hours'))
                    ->state(fn (Subfleet $record): string => $record->aircraft_count > 0
                        ? number_format(((int) $record->aircraft_sum_flight_time / 60) / $record->aircraft_count, 1).' h'
                        : '—'),
            ])
            ->headerActions([
                Action::make('exportCsv')
                    ->label(__('filament.reports_fleet_export'))
                    ->icon('tabler-download')
                    ->action(fn (): BinaryFileResponse => $this->exportCsv()),
            ])
            ->paginated([10, 25, 50]);
    }

    /**
     * The rows backing both the table and the CSV export, filtered by the
     * Reports page airline filter when one is selected.
     *
     * @return Builder<Subfleet>
     */
    private function baseQuery(): Builder
    {
        $airline_id = $this->pageFilters['airline_id'] ?? null;

        return Subfleet::query()
            ->when(
                filled($airline_id),
                fn (Builder $query): Builder => $query->where('airline_id', $airline_id),
            )
            ->withCount('aircraft')
            ->withSum('aircraft', 'flight_time')
            ->orderByDesc('aircraft_sum_flight_time');
    }

    /**
     * Stream the same rows the table shows to a CSV download.
     *
     * @throws CannotInsertRecord
     */
    private function exportCsv(): BinaryFileResponse
    {
        $subfleets = $this->baseQuery()->get();

        // Unique name so two exports in the same second can't truncate each other.
        $path = storage_path('app/import/fleet-utilization-'.now()->format('Ymd-His').'-'.Str::uuid().'.csv');
        Storage::makeDirectory('import');

        $writer = app(ExportService::class)->openCsv($path);
        $writer->insertOne([
            __('common.subfleet'),
            __('common.type'),
            __('common.aircraft'),
            __('filament.reports_fleet_total_hours'),
            __('filament.reports_fleet_avg_hours'),
        ]);

        foreach ($subfleets as $subfleet) {
            $total_hours = (int) $subfleet->aircraft_sum_flight_time / 60;
            $avg_hours = $subfleet->aircraft_count > 0 ? $total_hours / $subfleet->aircraft_count : 0;

            $writer->insertOne([
                $subfleet->name,
                $subfleet->type,
                $subfleet->aircraft_count,
                number_format($total_hours, 1),
                number_format($avg_hours, 1),
            ]);
        }

        return response()
            ->download($path, 'fleet-utilization.csv', ['content-type' => 'text/csv'])
            ->deleteFileAfterSend(true);
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
