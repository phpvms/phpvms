<?php

declare(strict_types=1);

namespace App\Filament\Resources\Airlines\Pages;

use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Resources\Airlines\AirlineResource;
use Filament\Resources\Pages\CreateRecord;
use Override;

class CreateAirline extends CreateRecord
{
    use ReversePrimaryButtons;

    protected static string $resource = AirlineResource::class;

    #[Override]
    protected function getFormActions(): array
    {
        return $this->reversePrimaryButtons(parent::getFormActions());
    }
}
