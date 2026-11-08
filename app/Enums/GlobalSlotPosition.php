<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Where a global slot row is emitted: in the head after the theme's CSS,
 * in the head after the theme's head inserts, or just before `</body>`.
 */
enum GlobalSlotPosition: string implements HasLabel
{
    case CSS = 'css';
    case HEAD = 'head';
    case FOOTER = 'footer';

    public function getLabel(): string
    {
        return match ($this) {
            self::CSS    => __('filament.global_slots.position_css'),
            self::HEAD   => __('filament.global_slots.position_head'),
            self::FOOTER => __('filament.global_slots.position_footer'),
        };
    }
}
