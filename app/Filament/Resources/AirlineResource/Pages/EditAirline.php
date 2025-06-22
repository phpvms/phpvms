<?php

namespace App\Filament\Resources\AirlineResource\Pages;

use App\Filament\Resources\AirlineResource;
use App\Models\Airline;
use App\Models\File;
use App\Services\FileService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAirline extends EditRecord
{
    protected static string $resource = AirlineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make()->before(function (Airline $record): void {
                $record->files()->each(function (File $file): void {
                    app(FileService::class)->removeFile($file);
                });
            }),
            Actions\RestoreAction::make(),
        ];
    }
}
