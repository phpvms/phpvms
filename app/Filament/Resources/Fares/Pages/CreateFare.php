<?php

declare(strict_types=1);

namespace App\Filament\Resources\Fares\Pages;

use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Resources\Fares\FareResource;
use Filament\Resources\Pages\CreateRecord;
use Override;

class CreateFare extends CreateRecord
{
    use ReversePrimaryButtons;

    protected static string $resource = FareResource::class;

    #[Override]
    protected function getFormActions(): array
    {
        return $this->reversePrimaryButtons(parent::getFormActions());
    }
}
