<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use App\Services\UserService;

class UserObserver
{
    public function __construct(private readonly UserService $userSvc) {}

    /**
     * After a user has been created, do some stuff
     */
    public function created(User $user): void
    {
        $this->userSvc->findAndSetPilotId($user);
    }
}
