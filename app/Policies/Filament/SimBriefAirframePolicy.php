<?php

declare(strict_types=1);

namespace App\Policies\Filament;

use App\Policies\BasePolicy;

class SimBriefAirframePolicy extends BasePolicy
{
    protected string $subject = 'sim-brief-airframe';
}
