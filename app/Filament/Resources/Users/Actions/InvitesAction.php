<?php

namespace App\Filament\Resources\Users\Actions;

use App\Filament\Resources\Invites\InviteResource;
use App\Models\Invite;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;

class InvitesAction
{
    public static function make(): Action
    {
        return Action::make('invites')
            ->label(__('common.invite'))
            ->color('gray')
            ->icon(Phosphor::EnvelopeLight)
            ->url(InviteResource::getUrl())
            ->visible(fn (): bool => auth()->user()?->can('view-any', arguments: Invite::class) && setting('general.invite_only_registrations', false));
    }
}
