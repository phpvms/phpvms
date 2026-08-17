<?php

namespace App\Filament\Resources\Users\Actions;

use App\Filament\Resources\UserFields\UserFieldResource;
use App\Models\UserField;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;

class UserFieldsAction
{
    public static function make(): Action
    {
        return Action::make('userfields')
            ->label(trans_choice('common.user_field', 2))
            ->color('gray')
            ->icon(Phosphor::ClipboardTextLight)
            ->url(UserFieldResource::getUrl())
            ->visible(fn (): bool => auth()->user()?->can('view-any', UserField::class));
    }
}
