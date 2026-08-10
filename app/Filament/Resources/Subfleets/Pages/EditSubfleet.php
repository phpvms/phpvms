<?php

namespace App\Filament\Resources\Subfleets\Pages;

use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Concerns\StacksRelationManagers;
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
    use StacksRelationManagers;

    protected static string $resource = SubfleetResource::class;

    /**
     * The six relation managers (aircraft, ranks, typeratings, fares,
     * expenses, files) are appended by the trait.
     *
     * @return array<string, string>
     */
    protected function jumpBarFormSections(): array
    {
        return [
            'subfleet-information'   => __('filament.subfleet_information'),
            'operational-capability' => __('filament.subfleets.sections.operational_capability'),
        ];
    }

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
