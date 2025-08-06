<?php

namespace App\Filament\Resources\FlightResource\Pages;

use App\Filament\Resources\FlightResource;
use Carbon\Carbon;
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

        $data['flight_time'] = Carbon::createFromTime(
            (int) ($data['flight_time'] / 60),
            $data['flight_time'] % 60,
            0
        );

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $flt_time = Carbon::parse($data['flight_time']);
        $data['flight_time'] = $flt_time->hour * 60 + $flt_time->minute;

        return $data;
    }
}
