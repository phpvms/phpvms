<?php

namespace App\Filament\Resources\Airports\Pages;

use App\Filament\Resources\Airports\AirportResource;
use App\Models\Airport;
use App\Models\File;
use App\Services\FileService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditAirport extends EditRecord
{
    protected static string $resource = AirportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make()->before(function (Airport $record) {
                $record->files()->each(function (File $file) {
                    app(FileService::class)->removeFile($file);
                });
            }),
            RestoreAction::make(),
        ];
    }
}
