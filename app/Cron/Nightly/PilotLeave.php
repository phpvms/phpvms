<?php

namespace App\Cron\Nightly;

use App\Contracts\Listener;
use App\Events\CronNightly;
use App\Services\UserService;
use Illuminate\Support\Facades\Log;

/**
 * Determine if any pilots should be set to ON LEAVE status.
 */
class PilotLeave extends Listener
{
    private UserService $userSvc;

    /**
     * PilotLeave constructor.
     */
    public function __construct(UserService $userSvc)
    {
        $this->userSvc = $userSvc;
    }

    /**
     * Set any users to being on leave after X days.
     *
     *
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    public function handle(CronNightly $event): void
    {
        Log::info('Cron: Running pilot leave check');
        $users = $this->userSvc->findUsersOnLeave();
        Log::info('Found '.count($users).' users on leave');

        foreach ($users as $user) {
            Log::info('Setting user '.$user->ident.' to ON LEAVE status');
            $this->userSvc->setStatusOnLeave($user);
        }
    }
}
