<?php

namespace App\Filament\Resources\Subfleets\Pages;

use App\Filament\Actions\ExportAction as OldExportAction;
use App\Filament\Actions\ImportAction as OldImportAction;
use App\Filament\Exports\SubfleetExporter;
use App\Filament\Imports\SubfleetImporter;
use App\Filament\Resources\Subfleets\SubfleetResource;
use App\Models\Enums\ImportExportType;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListSubfleets extends ListRecords
{
    protected static string $resource = SubfleetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            OldExportAction::make('export')
                ->arguments(['resourceTitle' => 'subfleets', 'exportType' => ImportExportType::SUBFLEETS]),

            OldImportAction::make('import')
                ->arguments(['resourceTitle' => 'subfleets', 'importType' => ImportExportType::SUBFLEETS]),

            ImportAction::make('import')
                ->visible(config('phpvms.use_queued_filament_imports'))
                ->importer(SubfleetImporter::class),

            ExportAction::make('export')
                ->visible(config('phpvms.use_queued_filament_imports'))
                ->exporter(SubfleetExporter::class),

            CreateAction::make()
                ->icon(Heroicon::OutlinedPlusCircle),
        ];
    }
}
