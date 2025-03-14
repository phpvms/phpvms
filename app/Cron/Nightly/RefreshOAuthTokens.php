<?php

namespace App\Cron\Nightly;

use App\Contracts\Listener;
use App\Events\CronNightly;
use App\Services\OAuthService;

class RefreshOAuthTokens extends Listener
{
    public function __construct(
        private readonly OAuthService $oAuthSvc
    ) {}

    public function handle(CronNightly $event): void
    {
        $this->oAuthSvc->refreshTokensBeforeTheyExpire();
    }
}
