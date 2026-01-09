<?php

namespace App\Filament\Resources\Fares\Pages;

use App\Filament\Actions\ExportAction as OldExportAction;
use App\Filament\Actions\ImportAction as OldImportAction;
use App\Filament\Exports\FareExporter;
use App\Filament\Imports\FareImporter;
use App\Filament\Resources\Fares\FareResource;
use App\Models\Enums\ImportExportType;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListFares extends ListRecords
{
    protected static string $resource = FareResource::class;

    protected function getHeaderActions(): array
    {
        return [
            OldExportAction::make('export')
                ->arguments(['resourceTitle' => 'fares', 'exportType' => ImportExportType::FARES]),

            OldImportAction::make('import')
                ->arguments(['resourceTitle' => 'fares', 'importType' => ImportExportType::FARES]),

            ImportAction::make('import')
                ->visible(config('phpvms.use_queued_filament_imports'))
                ->importer(FareImporter::class),

            ExportAction::make('export')
                ->visible(config('phpvms.use_queued_filament_imports'))
                ->exporter(FareExporter::class),

            CreateAction::make()
                ->icon(Heroicon::OutlinedPlusCircle),
        ];
    }
}
