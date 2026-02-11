<?php

namespace App\Filament\Resources\Flights\Pages;

use App\Filament\Actions\ExportAction as OldExportAction;
use App\Filament\Actions\ImportAction as OldImportAction;
use App\Filament\Exports\FlightExporter;
use App\Filament\Imports\FlightImporter;
use App\Filament\Resources\Flights\FlightResource;
use App\Models\Enums\ImportExportType;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListFlights extends ListRecords
{
    protected static string $resource = FlightResource::class;

    protected function getHeaderActions(): array
    {
        return [
            OldExportAction::make('export')
                ->arguments(['resourceTitle' => 'flights', 'exportType' => ImportExportType::FLIGHTS]),

            OldImportAction::make('import')
                ->arguments(['resourceTitle' => 'flights', 'importType' => ImportExportType::FLIGHTS]),

            ImportAction::make('import')
                ->visible(config('phpvms.use_queued_filament_imports'))
                ->importer(FlightImporter::class),

            ExportAction::make('export')
                ->visible(config('phpvms.use_queued_filament_imports'))
                ->exporter(FlightExporter::class),

            CreateAction::make()
                ->icon(Heroicon::OutlinedPlusCircle),
        ];
    }
}
