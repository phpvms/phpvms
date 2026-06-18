<?php

declare(strict_types=1);

namespace App\Policies\Filament;

use App\Policies\BasePolicy;

class UserPolicy extends BasePolicy
{
    protected string $subject = 'user';
}
