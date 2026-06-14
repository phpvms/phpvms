<?php

declare(strict_types=1);

namespace App\Policies\Filament;

use App\Policies\BasePolicy;

/**
 * Pirep comments are managed under the Pireps resource (as a relation), so they
 * reuse the `pirep` subject's permissions rather than defining their own.
 */
class PirepCommentPolicy extends BasePolicy
{
    protected string $subject = 'pirep';
}
