<?php

namespace App\Filament\Resources\AirframeResource\Pages;

use App\Filament\Resources\AirframeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageAirframes extends ManageRecords
{
    protected static string $resource = AirframeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
