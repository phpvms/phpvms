<?php

namespace App\Filament\Resources\Users\Actions;

use App\Models\User;
use Filafly\Icons\Phosphor\Enums\Phosphor;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class RequestEmailVerificationAction
{
    public static function make(): Action
    {
        return
            Action::make('request_email_verification')
                ->label(__('filament.user_request_email_verification'))
                ->icon(Phosphor::PaperPlaneTiltLight)
                ->tooltip(__('filament.user_request_email_verification'))
                ->iconButton()
                ->visible(fn (?User $record): bool => $record instanceof User && !$record->hasVerifiedEmail())
                ->action(function (User $record): void {
                    $record->sendEmailVerificationNotification();

                    Notification::make()
                        ->title(__('filament.user_email_verification_requested'))
                        ->success()
                        ->send();
                });
    }
}
