<?php

declare(strict_types=1);

namespace App\Contracts\Addons\Attributes;

use Attribute;

/**
 * Marks a class in an addon's `app/Listeners` as an event or hook listener.
 * A scanned class without it is not registered.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AddonListener {}
