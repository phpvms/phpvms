<?php

declare(strict_types=1);

namespace App\Policies\Filament;

use App\Policies\BasePolicy;

class PirepPolicy extends BasePolicy
{
    protected string $subject = 'pirep';
}
