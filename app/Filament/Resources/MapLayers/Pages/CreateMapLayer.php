<?php

declare(strict_types=1);

namespace App\Filament\Resources\MapLayers\Pages;

use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Resources\MapLayers\MapLayerResource;
use Filament\Resources\Pages\CreateRecord;
use Override;

class CreateMapLayer extends CreateRecord
{
    use ReversePrimaryButtons;

    protected static string $resource = MapLayerResource::class;

    #[Override]
    protected function getFormActions(): array
    {
        return $this->reversePrimaryButtons(parent::getFormActions());
    }
}
