<?php

declare(strict_types=1);

namespace Modules\Sample\Policies\Filament;

use App\Policies\BasePolicy;

class SampleTablePolicy extends BasePolicy
{
    protected string $subject = 'sample-table';
}
