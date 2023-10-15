<?php

namespace App\Filament\Resources\PirepResource\Pages;

use App\Filament\Resources\PirepResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPirep extends EditRecord
{
    protected static string $resource = PirepResource::class;

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
        $data['planned_distance'] = $data['planned_distance']->toUnit('nmi');
        $data['block_fuel'] = $data['block_fuel']->toUnit('lbs');
        $data['fuel_used'] = $data['fuel_used']->toUnit('lbs');

        return $data;
    }
}
