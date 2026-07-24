<?php

declare(strict_types=1);

namespace App\Filament\Resources\Fares\Pages;

use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Resources\Fares\FareResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Override;

class EditFare extends EditRecord
{
    use ReversePrimaryButtons;

    protected static string $resource = FareResource::class;

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
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
