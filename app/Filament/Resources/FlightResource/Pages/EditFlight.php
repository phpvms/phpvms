<?php

namespace App\Filament\Resources\FlightResource\Pages;

use App\Filament\Resources\FlightResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFlight extends EditRecord
{
    protected static string $resource = FlightResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['distance'] = $data['distance']->toUnit('nmi');
        $data['hours'] = (int) ($data['flight_time'] / 60);
        $data['minutes'] = $data['flight_time'] % 60;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['flight_time'] = $data['hours'] * 60 + $data['minutes'];

        return $data;
    }
}
