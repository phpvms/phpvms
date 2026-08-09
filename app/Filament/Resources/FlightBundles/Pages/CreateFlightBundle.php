<?php

declare(strict_types=1);

namespace App\Filament\Resources\FlightBundles\Pages;

use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Resources\FlightBundles\FlightBundleResource;
use Filament\Resources\Pages\CreateRecord;
use Override;

class CreateFlightBundle extends CreateRecord
{
    use ReversePrimaryButtons;

    protected static string $resource = FlightBundleResource::class;

    #[Override]
    protected function getFormActions(): array
    {
        return $this->reversePrimaryButtons(parent::getFormActions());
    }

    #[Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
