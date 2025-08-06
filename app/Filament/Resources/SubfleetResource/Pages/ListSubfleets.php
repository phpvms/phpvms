<?php

namespace App\Filament\Resources\SubfleetResource\Pages;

use App\Filament\Actions\ExportAction;
use App\Filament\Actions\ImportAction;
use App\Filament\Resources\SubfleetResource;
use App\Models\Enums\ImportExportType;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSubfleets extends ListRecords
{
    protected static string $resource = SubfleetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make('export')->arguments(['resourceTitle' => 'subfleets', 'exportType' => ImportExportType::SUBFLEETS]),
            ImportAction::make('import')->arguments(['resourceTitle' => 'subfleets', 'importType' => ImportExportType::SUBFLEETS]),
            CreateAction::make()->label('Add Subfleet')->icon('heroicon-o-plus-circle'),
        ];
    }
}
