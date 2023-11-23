<?php

namespace App\Filament\Resources\AircraftResource\Pages;

use App\Filament\Resources\AircraftResource;
use App\Models\Aircraft;
use App\Models\File;
use App\Services\FileService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAircraft extends EditRecord
{
    protected static string $resource = AircraftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make()->before(function (Aircraft $record) {
                $record->files()->each(function (File $file) {
                    app(FileService::class)->removeFile($file);
                });
            }),
            Actions\RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['fuel_onboard'] = $data['fuel_onboard']->toUnit(setting('units.fuel'));

        return $data;
    }
}
