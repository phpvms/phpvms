<?php

namespace App\Models\Observers;

use App\Models\User;

class UserObserver
{
    public function __construct(private readonly \App\Services\UserService $userSvc) {}

    /**
     * After a user has been created, do some stuff
     */
    public function created(User $user): void
    {
        $this->userSvc->findAndSetPilotId($user);
    }
}
