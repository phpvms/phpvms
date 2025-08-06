<?php

namespace App\Filament\Resources\AircraftResource\Pages;

use App\Filament\Resources\AircraftResource;
use App\Models\Aircraft;
use App\Models\File;
use App\Services\FileService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditAircraft extends EditRecord
{
    protected static string $resource = AircraftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make()->before(function (Aircraft $record) {
                $record->files()->each(function (File $file) {
                    app(FileService::class)->removeFile($file);
                });
            }),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['fuel_onboard'] = $data['fuel_onboard']->toUnit(setting('units.fuel'));

        $data['dow'] = $data['dow']->toUnit(setting('units.weight'));
        $data['zfw'] = $data['zfw']->toUnit(setting('units.weight'));
        $data['mtow'] = $data['mtow']->toUnit(setting('units.weight'));
        $data['mlw'] = $data['mlw']->toUnit(setting('units.weight'));

        return $data;
    }
}
