<?php

namespace App\Filament\Resources\Users\Actions;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class RequestEmailVerificationAction
{
    public static function make(): Action
    {
        return
            Action::make('request_email_verification')
                ->label(__('filament.user_request_email_verification'))
                ->color('warning')
                ->visible(fn (User $record): bool => $record->hasVerifiedEmail())
                ->action(function (User $record) {
                    $record->update([
                        'email_verified_at' => null,
                    ]);

                    $record->sendEmailVerificationNotification();

                    Notification::make()
                        ->title(__('filament.user_email_verification_requested'))
                        ->success()
                        ->send();
                });
    }
}
