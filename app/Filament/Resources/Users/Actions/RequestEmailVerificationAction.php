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
                ->label(fn (?User $record): string => $record?->hasVerifiedEmail()
                    ? __('filament.user_resend_email_verification')
                    : __('filament.user_request_email_verification'))
                ->icon(Phosphor::PaperPlaneTiltLight)
                ->tooltip(fn (?User $record): string => $record?->hasVerifiedEmail()
                    ? __('filament.user_resend_email_verification')
                    : __('filament.user_request_email_verification'))
                ->iconButton()
                ->visible(fn (?User $record): bool => $record instanceof User)
                // Only a verified email needs confirming: sending then marks it unverified.
                ->requiresConfirmation(fn (?User $record): bool => $record?->hasVerifiedEmail() ?? false)
                ->modalHeading(__('filament.user_reverify_email_heading'))
                ->modalDescription(__('filament.user_reverify_email_description'))
                ->action(function (User $record): void {
                    if ($record->hasVerifiedEmail()) {
                        $record->forceFill(['email_verified_at' => null])->save();
                    }

                    $record->sendEmailVerificationNotification();

                    Notification::make()
                        ->title(__('filament.user_email_verification_requested'))
                        ->success()
                        ->send();
                });
    }
}
