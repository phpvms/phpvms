<?php

namespace App\Filament\Resources\AirlineResource\Pages;

use App\Filament\Resources\AirlineResource;
use App\Models\Airline;
use App\Models\File;
use App\Services\FileService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditAirline extends EditRecord
{
    protected static string $resource = AirlineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make()->before(function (Airline $record) {
                $record->files()->each(function (File $file) {
                    app(FileService::class)->removeFile($file);
                });
            }),
            RestoreAction::make(),
        ];
    }
}
