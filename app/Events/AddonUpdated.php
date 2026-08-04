<?php

declare(strict_types=1);

namespace App\Events;

use App\Contracts\Event;
use App\Models\Addon;

/**
 * Fired after an installed addon is updated to a new version (files replaced,
 * row saved, assets relinked, panel cache busted).
 */
class AddonUpdated extends Event
{
    public function __construct(public Addon $addon) {}
}
