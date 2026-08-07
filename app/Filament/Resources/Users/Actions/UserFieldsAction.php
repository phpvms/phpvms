<?php

namespace App\Filament\Resources\Users\Actions;

use App\Filament\Resources\UserFields\UserFieldResource;
use App\Models\UserField;
use Daljo25\FilamentTablerIcons\Enums\TablerIcon;
use Filament\Actions\Action;

class UserFieldsAction
{
    public static function make(): Action
    {
        return Action::make('userfields')
            ->label(trans_choice('common.user_field', 2))
            ->icon(TablerIcon::ClipboardList)
            ->url(UserFieldResource::getUrl())
            ->visible(fn (): bool => auth()->user()?->can('view-any', UserField::class));
    }
}
