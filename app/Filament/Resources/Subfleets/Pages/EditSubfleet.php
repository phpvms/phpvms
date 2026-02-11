<?php

namespace App\Filament\Resources\Subfleets\Pages;

use App\Filament\Resources\Subfleets\SubfleetResource;
use App\Models\File;
use App\Models\Subfleet;
use App\Services\FileService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditSubfleet extends EditRecord
{
    protected static string $resource = SubfleetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make()->before(function (Subfleet $record) {
                $record->files()->each(function (File $file) {
                    app(FileService::class)->removeFile($file);
                });
            }),
            RestoreAction::make(),
        ];
    }
}
