<?php

namespace App\Notifications\Notifiables;

use Illuminate\Notifications\Notifiable;

/**
 * These are notifications that get broadcasted, not to a single person
 * (e.g, on Discord, Slack or Telegram or something)
 *
 * $notifyable = app(Broadcast::class);
 * $notifyable->notify($eventclass);
 */
class Broadcast
{
    use Notifiable;

    /**
     * Routing for Discord - the public channel ID that's used
     */
    public function routeNotificationForDiscord()
    {
        return setting('notifications.discord_public_channel_id');
    }
}
