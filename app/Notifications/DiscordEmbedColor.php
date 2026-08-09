<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * Embed colours for the broadcast Discord notifications.
 *
 * Preserves the palette the notifications carried before they moved onto
 * laravel-discord-notifier. That package's own DiscordColor enum holds
 * Discord's brand colours, which are not these — mapping onto it would have
 * restyled every embed (notably warning, an orange, whose nearest brand colour
 * is a yellow).
 *
 * Deliberately not in App\Enums: that namespace is for Filament-facing domain
 * enums and every member of it must implement HasLabel (see tests/Arch).
 */
enum DiscordEmbedColor: int
{
    case Success = 0x0B6623;

    case Warning = 0xFD6A02;

    case Error = 0xED2939;
}
