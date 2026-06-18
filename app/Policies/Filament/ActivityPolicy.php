<?php

declare(strict_types=1);

namespace App\Policies\Filament;

use App\Policies\BasePolicy;

/**
 * The Activity model lives in a vendor namespace, so this policy is registered
 * explicitly via Gate::policy() in AppServiceProvider rather than resolved by
 * the Models => Policies\Filament convention.
 */
class ActivityPolicy extends BasePolicy
{
    protected string $subject = 'activity';
}
