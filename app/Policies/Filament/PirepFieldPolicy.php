<?php

declare(strict_types=1);

namespace App\Policies\Filament;

use App\Policies\BasePolicy;

class PirepFieldPolicy extends BasePolicy
{
    protected string $subject = 'pirep-field';
}
