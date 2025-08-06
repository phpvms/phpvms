<?php

namespace App\Filament\Resources\FlightResource\Pages;

use App\Filament\Resources\FlightResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;

class CreateFlight extends CreateRecord
{
    protected static string $resource = FlightResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $flt_time = Carbon::parse($data['flight_time']);
        $data['flight_time'] = $flt_time->hour * 60 + $flt_time->minute;

        return $data;
    }
}
