<?php

namespace App\Filament\Resources\FareResource\Pages;

use App\Filament\Actions\ExportAction;
use App\Filament\Actions\ImportAction;
use App\Filament\Resources\FareResource;
use App\Models\Enums\ImportExportType;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFares extends ListRecords
{
    protected static string $resource = FareResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make('export')->arguments(['resourceTitle' => 'fares', 'exportType' => ImportExportType::FARES]),
            ImportAction::make('import')->arguments(['resourceTitle' => 'fares', 'importType' => ImportExportType::FARES]),
            CreateAction::make()->label('Add Fare')->icon('heroicon-o-plus-circle'),
        ];
    }
}
