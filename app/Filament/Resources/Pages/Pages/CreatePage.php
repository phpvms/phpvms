<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Resources\Pages\PageResource;
use Filament\Resources\Pages\CreateRecord;
use Override;

class CreatePage extends CreateRecord
{
    use ReversePrimaryButtons;

    protected static string $resource = PageResource::class;

    #[Override]
    protected function getFormActions(): array
    {
        return $this->reversePrimaryButtons(parent::getFormActions());
    }
}
