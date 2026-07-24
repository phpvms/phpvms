<?php

declare(strict_types=1);

namespace App\Filament\Resources\Ranks\Pages;

use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Resources\Ranks\RankResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Override;

class EditRank extends EditRecord
{
    use ReversePrimaryButtons;

    protected static string $resource = RankResource::class;

    #[Override]
    protected function getFormActions(): array
    {
        return $this->reversePrimaryButtons(parent::getFormActions());
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
