<?php

namespace App\Filament\Resources\Users\Actions;

use App\Filament\Resources\UserFields\UserFieldResource;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class UserFieldsAction
{
    public static function make(): Action
    {
        return Action::make('userfields')
            ->label(trans_choice('common.user_field', 2))
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->url(UserFieldResource::getUrl())
            ->visible(fn (): bool => auth()->user()?->can('view_any_user::field'));
    }
}
