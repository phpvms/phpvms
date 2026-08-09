<?php

declare(strict_types=1);

namespace App\Filament\Resources\Typeratings\Pages;

use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Resources\Typeratings\TyperatingResource;
use Filament\Resources\Pages\CreateRecord;
use Override;

class CreateTyperating extends CreateRecord
{
    use ReversePrimaryButtons;

    protected static string $resource = TyperatingResource::class;

    #[Override]
    protected function getFormActions(): array
    {
        return $this->reversePrimaryButtons(parent::getFormActions());
    }
}
