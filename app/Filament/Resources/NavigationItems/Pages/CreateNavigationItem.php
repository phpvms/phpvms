<?php

declare(strict_types=1);

namespace App\Filament\Resources\NavigationItems\Pages;

use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Resources\NavigationItems\NavigationItemResource;
use Filament\Resources\Pages\CreateRecord;
use Override;

class CreateNavigationItem extends CreateRecord
{
    use ReversePrimaryButtons;

    protected static string $resource = NavigationItemResource::class;

    #[Override]
    protected function getFormActions(): array
    {
        return $this->reversePrimaryButtons(parent::getFormActions());
    }
}
