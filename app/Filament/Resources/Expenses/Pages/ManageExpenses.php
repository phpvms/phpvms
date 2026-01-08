<?php

namespace App\Filament\Resources\Expenses\Pages;

use App\Filament\Actions\ExportAction as OldExportAction;
use App\Filament\Actions\ImportAction as OldImportAction;
use App\Filament\Exports\ExpenseExporter;
use App\Filament\Imports\ExpenseImporter;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Models\Enums\ImportExportType;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageExpenses extends ManageRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            OldExportAction::make('export')
                ->arguments(['resourceTitle' => 'expenses', 'exportType' => ImportExportType::EXPENSES]),

            OldImportAction::make('import')
                ->arguments(['resourceTitle' => 'expenses', 'importType' => ImportExportType::EXPENSES]),

            ImportAction::make('import')
                ->visible(config('phpvms.use_queued_filament_imports'))
                ->importer(ExpenseImporter::class),

            ExportAction::make('export')
                ->visible(config('phpvms.use_queued_filament_imports'))
                ->exporter(ExpenseExporter::class),

            CreateAction::make()
                ->icon(Heroicon::OutlinedPlusCircle),
        ];
    }
}
