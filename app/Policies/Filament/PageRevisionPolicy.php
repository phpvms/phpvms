<?php

declare(strict_types=1);

namespace App\Policies\Filament;

use App\Policies\BasePolicy;

/**
 * Revisions are managed under the Pages resource (as a relation), so they
 * reuse the `page` subject's permissions rather than defining their own.
 */
class PageRevisionPolicy extends BasePolicy
{
    protected string $subject = 'page';
}
