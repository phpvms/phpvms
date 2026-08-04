<?php

declare(strict_types=1);

namespace App\Events;

use App\Contracts\Event;
use App\Models\Addon;

/**
 * Fired after an addon is freshly installed and registered (files placed, row
 * saved, assets linked, panel cache busted).
 */
class AddonInstalled extends Event
{
    public function __construct(public Addon $addon) {}
}
