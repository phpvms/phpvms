<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What a navigation item points at.
 *
 * Only `PAGE` refers to a row in this database; the other two are strings the
 * admin supplies, which is why a navigation item stores a kind and a value
 * rather than a foreign key.
 */
enum NavigationTarget: int implements HasLabel
{
    /** A `pages.id`. */
    case PAGE = 0;

    /** A registered route name, e.g. `frontend.pilots.index`. */
    case ROUTE = 1;

    /** An absolute external URL. */
    case URL = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::PAGE  => 'Page',
            self::ROUTE => 'Route',
            self::URL   => 'URL',
        };
    }
}
