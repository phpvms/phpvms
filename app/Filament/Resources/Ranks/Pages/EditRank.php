<?php

declare(strict_types=1);

namespace App\Filament\Resources\Ranks\Pages;

use App\Filament\Concerns\PutsPrimaryActionLast;
use App\Filament\Resources\Ranks\RankResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Override;

class EditRank extends EditRecord
{
    use PutsPrimaryActionLast;

    protected static string $resource = RankResource::class;

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
