<?php

namespace App\Filament\Resources\Subfleets\Resources\Aircraft\Pages;

use App\Filament\Resources\Subfleets\Resources\Aircraft\AircraftResource;
use App\Support\Units\Mass;
use Filament\Resources\Pages\CreateRecord;

class CreateAircraft extends CreateRecord
{
    protected static string $resource = AircraftResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['dow'] = (filled($data['dow']) && $data['dow'] > 0) ? Mass::make((float) $data['dow'], setting('units.weight')) : null;
        $data['zfw'] = (filled($data['zfw']) && $data['zfw'] > 0) ? Mass::make((float) $data['dow'], setting('units.weight')) : null;
        $data['mtow'] = (filled($data['mtow']) && $data['mtow'] > 0) ? Mass::make((float) $data['dow'], setting('units.weight')) : null;
        $data['mlw'] = (filled($data['mlw']) && $data['mlw'] > 0) ? Mass::make((float) $data['dow'], setting('units.weight')) : null;

        return $data;
    }
}
