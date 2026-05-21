<?php

namespace App\Filament\Resources\FlightBundles\Resources\Flight\Pages;

use App\Filament\Resources\FlightBundles\Resources\Flight\FlightResource;
use App\Models\FlightBundle;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;

class CreateFlight extends CreateRecord
{
    protected static string $resource = FlightResource::class;

    #[\Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $flt_time = Carbon::parse($data['flight_time']);
        $data['flight_time'] = $flt_time->hour * 60 + $flt_time->minute;

        // Pull bundle_id from the parent route binding when missing.
        if (!isset($data['bundle_id'])) {
            $parent = $this->getParentRecord();
            if ($parent instanceof FlightBundle) {
                $data['bundle_id'] = $parent->getKey();
            }
        }

        return $data;
    }
}
