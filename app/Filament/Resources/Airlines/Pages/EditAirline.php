<?php

namespace App\Filament\Resources\Airlines\Pages;

use App\Filament\Concerns\AutosavesFields;
use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Resources\Airlines\AirlineResource;
use App\Filament\Resources\Airlines\Schemas\AirlineForm;
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
    use AutosavesFields;
    use ReversePrimaryButtons;

    protected static string $resource = AirlineResource::class;

    /**
     * Only the logo autosaves (the rest of the form saves on submit) — the
     * field wires itself through AirlineForm's afterStateUpdated hook.
     *
     * @return list<string>
     */
    protected function autosaveKeys(): array
    {
        return ['logo'];
    }

    protected function persistAutosavedField(string $key, mixed $value): void
    {
        /** @var Airline $record */
        $record = $this->getRecord();

        AirlineForm::persistLogo($record, is_string($value) ? $value : null);
    }

    protected function autosaveNotificationTitle(): string
    {
        return __('filament.airline_logo_saved');
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
            ForceDeleteAction::make()->before(function (Airline $record): void {
                $record->files()->each(function (File $file): void {
                    app(FileService::class)->removeFile($file);
                });
            }),
            RestoreAction::make(),
        ];
    }
}
