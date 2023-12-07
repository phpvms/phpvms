<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserFieldResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Widgets\UserStats;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('userfields')
                ->label('User Fields')
                ->icon('heroicon-o-clipboard-document-list')
                ->url(UserFieldResource::getUrl('index'))
                ->visible(fn (): bool => auth()->user()?->can('view_any_user::field')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            //UserStats::class
        ];
    }
}
