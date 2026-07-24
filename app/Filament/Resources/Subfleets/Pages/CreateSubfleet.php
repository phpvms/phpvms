<?php

declare(strict_types=1);

namespace App\Filament\Resources\Subfleets\Pages;

use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Resources\Subfleets\SubfleetResource;
use Filament\Resources\Pages\CreateRecord;
use Override;

class CreateSubfleet extends CreateRecord
{
    use ReversePrimaryButtons;

    protected static string $resource = SubfleetResource::class;

    #[Override]
    protected function getFormActions(): array
    {
        return $this->reversePrimaryButtons(parent::getFormActions());
    }
}
