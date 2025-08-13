<?php

namespace App\Filament\Resources\Flights\Pages;

use App\Filament\Actions\ExportAction;
use App\Filament\Actions\ImportAction;
use App\Filament\Resources\Flights\FlightResource;
use App\Models\Enums\ImportExportType;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListFlights extends ListRecords
{
    protected static string $resource = FlightResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make('export')
                ->arguments(['resourceTitle' => 'flights', 'exportType' => ImportExportType::FLIGHTS]),

            ImportAction::make('import')
                ->arguments(['resourceTitle' => 'flights', 'importType' => ImportExportType::FLIGHTS]),

            CreateAction::make()
                ->icon(Heroicon::OutlinedPlusCircle),
        ];
    }
}
