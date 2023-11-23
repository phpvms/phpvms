<?php

namespace App\Filament\Resources\AirportResource\Pages;

use App\Filament\Resources\AirportResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAirport extends EditRecord
{
    protected static string $resource = AirportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
