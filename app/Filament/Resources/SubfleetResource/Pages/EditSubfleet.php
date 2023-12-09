<?php

namespace App\Filament\Resources\SubfleetResource\Pages;

use App\Filament\Resources\AircraftResource;
use App\Filament\Resources\SubfleetResource;
use App\Models\File;
use App\Models\Subfleet;
use App\Services\FileService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSubfleet extends EditRecord
{
    protected static string $resource = SubfleetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('aircraft')
                ->url(fn (Subfleet $record): string => AircraftResource::getUrl('index').'?tableFilters[subfleet][value]='.$record->id)
                ->label('Aircraft')
                ->icon('heroicon-o-paper-airplane')
                ->color('success'),
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make()->before(function (Subfleet $record) {
                $record->files()->each(function (File $file) {
                    app(FileService::class)->removeFile($file);
                });
            }),
            Actions\RestoreAction::make(),
        ];
    }
}
