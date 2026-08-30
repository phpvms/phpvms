<?php

declare(strict_types=1);

namespace App\Policies\Filament;

use App\Policies\BasePolicy;

class OAuthConnectionPolicy extends BasePolicy
{
    protected string $subject = 'o-auth-connection';
}
