<?php

namespace App\Filament\Resources\FlightResource\Pages;

use App\Filament\Actions\ExportAction;
use App\Filament\Actions\ImportAction;
use App\Filament\Resources\FlightResource;
use App\Models\Enums\ImportExportType;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFlights extends ListRecords
{
    protected static string $resource = FlightResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make('export')->arguments(['resourceTitle' => 'flights', 'exportType' => ImportExportType::FLIGHTS]),
            ImportAction::make('import')->arguments(['resourceTitle' => 'flights', 'importType' => ImportExportType::FLIGHTS]),
            CreateAction::make()->label('Add Flight')->icon('heroicon-o-plus-circle'),
        ];
    }
}
