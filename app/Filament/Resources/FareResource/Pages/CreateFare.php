<?php

namespace App\Filament\Resources\FareResource\Pages;

use App\Filament\Resources\FareResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFare extends CreateRecord
{
    protected static string $resource = FareResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
