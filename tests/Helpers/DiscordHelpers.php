<?php

use Arthurpar06\DiscordNotifier\Messages\DiscordMessage;

/**
 * The serialized payload of a built Discord notification.
 *
 * @return array<string, mixed>
 */
function discordPayload(DiscordMessage $message): array
{
    return $message->toArray();
}

/**
 * The first embed of a built Discord notification.
 *
 * @return array<string, mixed>
 */
function discordEmbed(DiscordMessage $message): array
{
    return $message->toArray()['embeds'][0] ?? [];
}

/**
 * The first embed's fields, keyed by name with the bold markers stripped, so a
 * test can assert on the label it reads rather than its markup.
 *
 * @return array<string, string>
 */
function discordEmbedFields(DiscordMessage $message): array
{
    $fields = [];

    foreach (discordEmbed($message)['fields'] ?? [] as $field) {
        $fields[trim((string) $field['name'], '*_')] = $field['value'];
    }

    return $fields;
}
