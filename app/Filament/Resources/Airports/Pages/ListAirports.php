<?php

namespace App\Filament\Resources\Airports\Pages;

use App\Enums\ImportExportType;
use App\Filament\Actions\ExportAction as OldExportAction;
use App\Filament\Actions\ImportAction as OldImportAction;
use App\Filament\Exports\AirportExporter;
use App\Filament\Imports\AirportImporter;
use App\Filament\Resources\Airports\AirportResource;
use App\Models\Airport;
use App\Services\AirportService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Override;

class ListAirports extends ListRecords
{
    protected static string $resource = AirportResource::class;

    public string $bulkIcaoInput = '';

    /** @var array<int, array{icao: string, name: string, hub: bool, status: string}> */
    public array $bulkRows = [];

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            OldExportAction::make('old-export')
                ->arguments(['resourceTitle' => 'airports', 'exportType' => ImportExportType::AIRPORT]),

            OldImportAction::make('old-import')
                ->arguments(['resourceTitle' => 'airports', 'importType' => ImportExportType::AIRPORT]),

            ImportAction::make('import')
                ->visible(config('phpvms.use_queued_filament_imports'))
                ->importer(AirportImporter::class),

            ExportAction::make('export')
                ->visible(config('phpvms.use_queued_filament_imports'))
                ->exporter(AirportExporter::class),

            CreateAction::make()
                ->icon(Heroicon::OutlinedPlusCircle),

            Action::make('bulkAdd')
                ->label(__('airports.bulk_add'))
                ->icon(Heroicon::OutlinedRectangleStack)
                ->modalHeading(__('airports.bulk_add'))
                ->modalWidth(Width::TwoExtraLarge)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('common.close'))
                ->mountUsing(fn () => $this->resetBulkAdd())
                ->modalContent(fn (): Factory|View => view('filament.airports.bulk-add-modal')),
        ];
    }

    /**
     * Queue the pasted ICAO codes as pending rows, then let the browser drive
     * lookups one at a time (see processNextBulkAirport) so calls stay
     * sequential and rate-limited.
     */
    public function addBulkAirports(): void
    {
        if (!Gate::allows('create', Airport::class)) {
            Notification::make()->title(__('common.not_authorized'))->danger()->send();

            return;
        }

        $codes = collect(preg_split('/[\s,]+/', $this->bulkIcaoInput, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $code): string => strtoupper(trim($code)))
            ->filter()
            ->unique();

        foreach ($codes as $icao) {
            // Skip codes already listed so re-submitting the box doesn't duplicate rows.
            if (collect($this->bulkRows)->contains(fn (array $row): bool => $row['icao'] === $icao)) {
                continue;
            }

            $this->bulkRows[] = ['icao' => $icao, 'name' => '', 'hub' => false, 'status' => 'pending'];
        }

        $this->bulkIcaoInput = '';
        $this->dispatch('bulk-add-start');
    }

    /**
     * Look up and persist the next pending row. Returns the number of rows still
     * pending so the browser knows whether to keep going.
     */
    public function processNextBulkAirport(): int
    {
        $index = array_find_key($this->bulkRows, fn ($row): bool => $row['status'] === 'pending');
        if ($index === null) {
            return 0;
        }

        $icao = $this->bulkRows[$index]['icao'];
        $lookup = app(AirportService::class)->lookupAirport($icao);

        if (empty($lookup)) {
            $this->bulkRows[$index]['status'] = 'error';
        } else {
            $existing = Airport::withTrashed()->where('icao', $icao)->first();
            $status = $existing ? 'updated' : 'added';

            $airport = $existing ?? new Airport();
            $airport->fill($lookup);

            if ($existing?->trashed()) {
                $airport->restore();
            }

            $airport->save();

            $this->bulkRows[$index] = [
                'icao'   => $airport->icao,
                'name'   => (string) $airport->name,
                'hub'    => (bool) $airport->hub,
                'status' => $status,
            ];
        }

        return collect($this->bulkRows)->where('status', 'pending')->count();
    }

    public function toggleBulkHub(int $index): void
    {
        $row = $this->bulkRows[$index] ?? null;
        if ($row === null || $row['status'] === 'error' || $row['status'] === 'pending') {
            return;
        }

        $airport = Airport::where('icao', $row['icao'])->first();
        if ($airport === null) {
            return;
        }

        $airport->hub = !$airport->hub;
        $airport->save();

        $this->bulkRows[$index]['hub'] = (bool) $airport->hub;
    }

    public function resetBulkAdd(): void
    {
        $this->bulkIcaoInput = '';
        $this->bulkRows = [];
    }
}
