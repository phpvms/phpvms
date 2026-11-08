<?php

declare(strict_types=1);

namespace App\Support\Permissions;

use InvalidArgumentException;

/**
 * An API scope declaration; maps onto `PermissionRegistry::registerApiScope()`.
 */
final readonly class Scope
{
    public function __construct(
        public string $name,
        public string $group = 'API',
        public ?string $label = null,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Scope name must not be empty.');
        }
    }
}
