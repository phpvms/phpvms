<?php

declare(strict_types=1);

namespace App\Contracts\Addons\Attributes;

use Attribute;

/**
 * The addon's Eloquent models. Core drops the table of every class carrying it
 * when the addon is uninstalled with "Also remove its data".
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AddonModel {}
