<?php

declare(strict_types=1);

namespace App\Filament\Resources\MapLayers\Pages;

use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Resources\MapLayers\MapLayerResource;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Override;

class EditMapLayer extends EditRecord
{
    use ReversePrimaryButtons;

    protected static string $resource = MapLayerResource::class;

    #[Override]
    protected function getFormActions(): array
    {
        return $this->reversePrimaryButtons(parent::getFormActions());
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->icon(Phosphor::TrashLight),
        ];
    }
}
