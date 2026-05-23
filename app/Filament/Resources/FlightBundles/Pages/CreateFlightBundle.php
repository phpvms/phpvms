<?php

declare(strict_types=1);

namespace App\Filament\Resources\FlightBundles\Pages;

use App\Filament\Resources\FlightBundles\FlightBundleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFlightBundle extends CreateRecord
{
    protected static string $resource = FlightBundleResource::class;

    #[\Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
