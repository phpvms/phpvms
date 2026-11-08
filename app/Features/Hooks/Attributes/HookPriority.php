<?php

declare(strict_types=1);

namespace App\Features\Hooks\Attributes;

use Attribute;

/**
 * Run order for a hook listener: lower runs first. Listeners without it get 100.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class HookPriority
{
    public const int DEFAULT = 100;

    public function __construct(public int $priority = self::DEFAULT) {}
}
