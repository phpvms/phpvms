<?php

namespace App\Filament\Resources\Users\Actions;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Auth\Events\Verified;

class VerifyEmailAction
{
    public static function make(): Action
    {

        return Action::make('verify_email')
            ->label(__('filament.user_verify_email'))
            ->visible(fn (User $record): bool => !$record->hasVerifiedEmail())
            ->color('danger')
            ->action(function (User $record) {
                if ($record->markEmailAsVerified()) {
                    event(new Verified($record));
                }

                Notification::make()
                    ->title(__('filament.user_email_verified'))
                    ->success()
                    ->send();
            });
    }
}
