<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Concerns\ReversePrimaryButtons;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\UserService;
use Filament\Resources\Pages\CreateRecord;
use Override;

class CreateUser extends CreateRecord
{
    use ReversePrimaryButtons;

    protected static string $resource = UserResource::class;

    #[Override]
    protected function getFormActions(): array
    {
        return $this->reversePrimaryButtons(parent::getFormActions());
    }

    #[Override]
    protected function handleRecordCreation(array $data): User
    {
        if (isset($data['transfer_time'])) {
            $data['transfer_time'] *= 60;
        }

        return app(UserService::class)->createUser($data, $data['roles'] ?? [], $data['state'] ?? null);
    }
}
