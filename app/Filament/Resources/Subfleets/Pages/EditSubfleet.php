<?php

namespace App\Filament\Resources\Subfleets\Pages;

use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Resources\Subfleets\SubfleetResource;
use App\Models\File;
use App\Models\Subfleet;
use App\Services\FileService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Override;

class EditSubfleet extends EditRecord
{
    use ReversePrimaryButtons;

    protected static string $resource = SubfleetResource::class;

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
            ForceDeleteAction::make()->before(function (Subfleet $record): void {
                $record->files()->each(function (File $file): void {
                    app(FileService::class)->removeFile($file);
                });
            }),
            RestoreAction::make(),
        ];
    }
}
