<?php

declare(strict_types=1);

namespace App\Filament\Resources\Ranks\Pages;

use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Resources\Ranks\RankResource;
use Filament\Resources\Pages\CreateRecord;
use Override;

class CreateRank extends CreateRecord
{
    use ReversePrimaryButtons;

    protected static string $resource = RankResource::class;

    #[Override]
    protected function getFormActions(): array
    {
        return $this->reversePrimaryButtons(parent::getFormActions());
    }
}
