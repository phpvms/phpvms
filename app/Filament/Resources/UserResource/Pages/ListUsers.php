<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\InviteResource;
use App\Filament\Resources\UserFieldResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Widgets\UserStats;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add User')
                ->icon('heroicon-o-plus-circle'),

            Action::make('userfields')
                ->label('User Fields')
                ->icon('heroicon-o-clipboard-document-list')
                ->url(UserFieldResource::getUrl())
                ->visible(fn (): bool => auth()->user()?->can('view_any_user::field')),

            Action::make('invites')
                ->label('Invites')
                ->icon('heroicon-o-envelope')
                ->url(InviteResource::getUrl())
                ->visible(fn (): bool => auth()->user()?->can('view_any_invite') && setting('general.invite_only_registrations', false)),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // UserStats::class
        ];
    }
}
