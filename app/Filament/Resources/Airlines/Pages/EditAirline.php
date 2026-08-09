<?php

namespace App\Filament\Resources\Airlines\Pages;

use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Resources\Airlines\AirlineResource;
use App\Models\Airline;
use App\Models\File;
use App\Services\FileService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Override;

class EditAirline extends EditRecord
{
    use ReversePrimaryButtons;

    protected static string $resource = AirlineResource::class;

    #[Override]
    protected function getFormActions(): array
    {
        return $this->reversePrimaryButtons(parent::getFormActions());
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make()->before(function (Airline $record): void {
                $record->files()->each(function (File $file): void {
                    app(FileService::class)->removeFile($file);
                });
            }),
            RestoreAction::make(),
        ];
    }
}
