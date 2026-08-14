<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Actions\InvitesAction;
use App\Filament\Resources\Users\Actions\UserFieldsAction;
use App\Filament\Resources\Users\UserResource;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon(TablerIcon::CirclePlus),
            UserFieldsAction::make(),
            InvitesAction::make(),
        ];
    }
}
