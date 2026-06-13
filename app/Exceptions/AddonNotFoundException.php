<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by AddonRegistry::findOrFail() when no addon matches the given name.
 */
class AddonNotFoundException extends RuntimeException
{
    public function __construct(string $name)
    {
        parent::__construct(sprintf('Addon [%s] not found.', $name));
    }
}
