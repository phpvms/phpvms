<?php

declare(strict_types=1);

namespace Modules\PhpvmsAirsideTheme\Support;

/**
 * Maps a state colour name, as the DTOs emit it (success|warning|danger|info|gray),
 * to the TallStackUI colour used for a state badge. Any other name is slate.
 */
final class StateColor
{
    public static function tallstack(?string $stateColor): string
    {
        return match ($stateColor) {
            'success' => 'green',
            'warning' => 'amber',
            'danger'  => 'red',
            'info'    => 'sky',
            default   => 'slate',
        };
    }
}
