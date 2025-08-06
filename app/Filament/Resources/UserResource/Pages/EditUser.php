<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Events\UserStateChanged;
use App\Events\UserStatsChanged;
use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Hash;

class EditUser extends EditRecord
{
    private ?int $oldState = null;

    private ?int $oldRankId = null;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('verify_email')->label('Verify Email')->action(function (User $record) {
                if ($record->markEmailAsVerified()) {
                    event(new Verified($record));
                }

                Notification::make()
                    ->title('User\'s email verified successfully')
                    ->success()
                    ->send();
            })->visible(fn (User $record): bool => !$record->hasVerifiedEmail()),
            Action::make('request_email_verification')->label('Request new email verification')->action(function (User $record) {
                $record->update([
                    'email_verified_at' => null,
                ]);

                $record->sendEmailVerificationNotification();

                Notification::make()
                    ->title('User email verification requested successfully')
                    ->success()
                    ->send();
            })->color('warning')->visible(fn (User $record): bool => $record->hasVerifiedEmail()),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
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

    protected function beforeSave(): void
    {
        if ($this->record instanceof User) {
            $this->oldState = $this->record->state;
            $this->oldRankId = $this->record->rank_id;
        }
    }

    protected function afterSave(): void
    {
        if ($this->record instanceof User && $this->oldState !== $this->record->state) {
            event(new UserStateChanged($this->record, $this->oldState));
        }

        if ($this->record instanceof User && $this->oldRankId !== $this->record->rank_id) {
            event(new UserStatsChanged($this->record, 'rank', $this->oldRankId));
        }
    }
}
