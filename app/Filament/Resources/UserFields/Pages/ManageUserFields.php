<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserFields\Pages;

use App\Filament\Resources\UserFields\UserFieldResource;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Override;

class ManageUserFields extends ManageRecords
{
    protected static string $resource = UserFieldResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon(Phosphor::PlusCircleLight),
        ];
    }
}
