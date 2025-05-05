<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Hash;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('verify_email')->label('Verify Email')->action(function (User $record) {
                if ($record->markEmailAsVerified()) {
                    event(new Verified($record));
                }

                Notification::make()
                    ->title('User\'s email verified successfully')
                    ->success()
                    ->send();
            })->visible(fn (User $record): bool => !$record->hasVerifiedEmail()),
            Actions\Action::make('request_email_verification')->label('Request new email verification')->action(function (User $record) {
                $record->update([
                    'email_verified_at' => null,
                ]);

                $record->sendEmailVerificationNotification();

                Notification::make()
                    ->title('User email verification requested successfully')
                    ->success()
                    ->send();
            })->color('warning')->visible(fn (User $record): bool => $record->hasVerifiedEmail()),
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['name'] = $this->record->name;
        $data['email'] = $this->record->email;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        return $data;
    }
}
