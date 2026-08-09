<?php

namespace App\Notifications\Messages\Broadcast;

use App\Contracts\Notification;
use App\Models\User;
use App\Notifications\DiscordEmbedColor;
use Arthurpar06\DiscordNotifier\Embeds\DiscordEmbed;
use Arthurpar06\DiscordNotifier\Embeds\DiscordEmbedAuthor;
use Arthurpar06\DiscordNotifier\Messages\DiscordMessage;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Tell the staff channel that a user registered.
 */
class UserRegistered extends Notification implements ShouldQueue
{
    /**
     * Create a new notification instance.
     */
    public function __construct(private readonly User $user) {}

    /**
     * @return string[]
     */
    public function via($notifiable): array
    {
        return ['discord'];
    }

    /**
     * Send a Discord notification. The destination comes from the notifiable,
     * so this only builds content.
     */
    public function toDiscord($notifiable): DiscordMessage
    {
        return DiscordMessage::make()->embed(
            DiscordEmbed::make()
                ->color(DiscordEmbedColor::Success->value)
                ->title(__('notifications.discord.user_registered', ['ident' => $this->user->ident]))
                ->author(DiscordEmbedAuthor::make($this->user->ident.' - '.$this->user->name_private)
                    ->iconUrl($this->user->resolveAvatarUrl()))
                ->timestamp(now())
        );
    }

    public function toArray($notifiable): array
    {
        return [
            'user_id' => $this->user->id,
        ];
    }
}
