<?php

namespace App\Filament\Resources\FlightBundles\Resources\Flight\Pages;

use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Resources\FlightBundles\Resources\Flight\FlightResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Override;

class CreateFlight extends CreateRecord
{
    use ReversePrimaryButtons;

    protected static string $resource = FlightResource::class;

    #[Override]
    protected function getFormActions(): array
    {
        return $this->reversePrimaryButtons(parent::getFormActions());
    }

    #[Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $flt_time = Carbon::parse($data['flight_time']);
        $data['flight_time'] = $flt_time->hour * 60 + $flt_time->minute;

        return $data;
    }
}
