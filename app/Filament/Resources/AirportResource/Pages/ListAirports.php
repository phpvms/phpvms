<?php

namespace App\Filament\Resources\AirportResource\Pages;

use Filament\Actions;
use App\Filament\Resources\AirportResource;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Actions\ExportAction;
use App\Filament\Actions\ImportAction;
use App\Models\Enums\ImportExportType;

class ListAirports extends ListRecords
{
    protected static string $resource = AirportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make('export')->arguments(['resourceTitle' => 'airport', 'exportType' => ImportExportType::AIRPORT]),
            ImportAction::make('import')->arguments(['resourceTitle' => 'airport', 'importType' => ImportExportType::AIRPORT]),
            Actions\CreateAction::make()->label('Add Airport')->icon('heroicon-o-plus-circle'),
        ];
    }
}
