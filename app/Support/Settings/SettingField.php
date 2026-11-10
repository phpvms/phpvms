<?php

declare(strict_types=1);

namespace App\Support\Settings;

use Attribute;

/**
 * How a settings-class property is presented on the settings pages.
 *
 * Every argument is optional. Without a label, core settings use the
 * translation `settings.{group}.{name}.label` (and `.description`), and addon
 * settings use the property name in headline case. A label or description given
 * here is passed through `__()`, so it may be a literal or a translation key.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class SettingField
{
    public function __construct(
        public ?string $label = null,
        public ?string $description = null,
        public bool $hidden = false,
    ) {}
}
