<?php

namespace App\Filament\Resources\Airports\Pages;

use App\Filament\Actions\ExportAction as OldExportAction;
use App\Filament\Actions\ImportAction as OldImportAction;
use App\Filament\Exports\AirportExporter;
use App\Filament\Imports\AirportImporter;
use App\Filament\Resources\Airports\AirportResource;
use App\Models\Enums\ImportExportType;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListAirports extends ListRecords
{
    protected static string $resource = AirportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            OldExportAction::make('export')
                ->arguments(['resourceTitle' => 'airports', 'exportType' => ImportExportType::AIRPORT]),

            OldImportAction::make('import')
                ->arguments(['resourceTitle' => 'airports', 'importType' => ImportExportType::AIRPORT]),

            ImportAction::make('import')
                ->visible(config('phpvms.use_queued_filament_imports'))
                ->importer(AirportImporter::class),

            ExportAction::make('export')
                ->visible(config('phpvms.use_queued_filament_imports'))
                ->exporter(AirportExporter::class),

            CreateAction::make()
                ->icon(Heroicon::OutlinedPlusCircle),
        ];
    }
}
