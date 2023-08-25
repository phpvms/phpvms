<?php

namespace App\Filament\Resources\FlightResource\Pages;

use App\Filament\Resources\FlightResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFlights extends ListRecords
{
    protected static string $resource = FlightResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Export to CSV'),
            Actions\CreateAction::make()->label('Import from CSV'),
            Actions\CreateAction::make()->label('Add Flight'),
        ];
    }
}
