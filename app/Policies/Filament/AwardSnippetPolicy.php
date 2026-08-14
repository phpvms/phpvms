<?php

declare(strict_types=1);

namespace App\Policies\Filament;

use App\Policies\BasePolicy;

class AwardSnippetPolicy extends BasePolicy
{
    protected string $subject = 'award-snippet';
}
