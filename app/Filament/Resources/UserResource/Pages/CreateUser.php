<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): User
    {
        if (isset($data['transfer_time'])) {
            $data['transfer_time'] *= 60;
        }

        return app(UserService::class)->createUser($data, $data['roles'] ?? [], $data['state'] ?? null);
    }
}
