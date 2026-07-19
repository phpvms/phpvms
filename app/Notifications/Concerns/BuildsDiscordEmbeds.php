<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

use App\Models\User;
use Arthurpar06\DiscordNotifier\Embeds\DiscordEmbed;

/**
 * Shared pieces of the broadcast Discord embeds, so the Discord-specific
 * workarounds live in one place rather than drifting across message classes.
 */
trait BuildsDiscordEmbeds
{
    /**
     * An avatar URL Discord accepts as an embed thumbnail.
     *
     * $user->resolveAvatarUrl() is somehow not accepted by Discord, so this
     * uses the uploaded avatar's URL and falls back to a gravatar when the user
     * has none.
     */
    protected function discordAvatarUrl(User $user): string
    {
        return $user->avatar->url ?? $user->gravatar(256);
    }

    /**
     * Add fields to an embed, keeping the bolded name and inline layout that
     * the previous embed builder applied to every field.
     *
     * @param array<string, string> $fields
     */
    protected function addDiscordFields(DiscordEmbed $embed, array $fields): DiscordEmbed
    {
        foreach ($fields as $name => $value) {
            $embed->field('**'.$name.'**', (string) $value, true);
        }

        return $embed;
    }
}
