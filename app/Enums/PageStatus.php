<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Whether a page's content is live.
 *
 * Orthogonal to `pages.enabled`, which is the admin's on/off switch for the page
 * as a whole. A page can be enabled and still be a draft that nobody has ever
 * published; both must be satisfied before a visitor sees it.
 */
enum PageStatus: int implements HasLabel
{
    case DRAFT = 0;
    case PUBLISHED = 1;

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT     => 'Draft',
            self::PUBLISHED => 'Published',
        };
    }
}
