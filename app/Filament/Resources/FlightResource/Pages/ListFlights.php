<?php

namespace App\Filament\Resources\FlightResource\Pages;

use App\Filament\Resources\FlightResource;
use App\Models\Enums\ImportExportType;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFlights extends ListRecords
{
    protected static string $resource = FlightResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Export to CSV'),
            Actions\Action::make('import')->label('Import from CSV')->action('importFlights'),
            Actions\CreateAction::make()->label('Add Flight'),
        ];
    }

    protected function importFlights()
    {
        $this->importFile($request, ImportExportType::FLIGHTS);
    }
}
