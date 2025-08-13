<?php

namespace App\Filament\Resources\Users\Pages;

use App\Events\UserStateChanged;
use App\Events\UserStatsChanged;
use App\Filament\Resources\Users\Actions\RequestEmailVerificationAction;
use App\Filament\Resources\Users\Actions\VerifyEmailAction;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Hash;

class EditUser extends EditRecord
{
    private ?int $oldState = null;

    private ?int $oldRankId = null;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            VerifyEmailAction::make(),
            RequestEmailVerificationAction::make(),
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
