<?php

namespace App\Filament\Resources\AirportResource\Pages;

use App\Filament\Actions\ExportAction;
use App\Filament\Actions\ImportAction;
use App\Filament\Resources\AirportResource;
use App\Models\Enums\ImportExportType;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAirports extends ListRecords
{
    protected static string $resource = AirportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make('export')->arguments(['resourceTitle' => 'airports', 'exportType' => ImportExportType::AIRPORT]),
            ImportAction::make('import')->arguments(['resourceTitle' => 'airports', 'importType' => ImportExportType::AIRPORT]),
            CreateAction::make()->label('Add Airport')->icon('heroicon-o-plus-circle'),
        ];
    }
}
