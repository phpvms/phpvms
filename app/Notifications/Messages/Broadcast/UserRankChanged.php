<?php

namespace App\Notifications\Messages\Broadcast;

use App\Contracts\Notification;
use App\Models\User;
use App\Notifications\DiscordEmbedColor;
use Arthurpar06\DiscordNotifier\Embeds\DiscordEmbed;
use Arthurpar06\DiscordNotifier\Embeds\DiscordEmbedAuthor;
use Arthurpar06\DiscordNotifier\Messages\DiscordMessage;
use Illuminate\Contracts\Queue\ShouldQueue;

class UserRankChanged extends Notification implements ShouldQueue
{
    /**
     * Create a new notification instance.
     */
    public function __construct(private readonly User $user) {}

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
        $user = $this->user;

        // User avatar, somehow $user->resolveAvatarUrl() is not being accepted by Discord as thumbnail
        $user_avatar = empty($user->avatar)
            ? $user->gravatar(256)
            : $user->avatar->url;

        return DiscordMessage::make()->embed(
            DiscordEmbed::make()
                ->color(DiscordEmbedColor::Success->value)
                ->title(__('notifications.discord.rank_changed', ['rank' => $user->rank->name]))
                ->description($user->discord_id
                    ? __('notifications.discord.rank_changed_for', ['mention' => '<@'.$user->discord_id.'>'])
                    : null)
                ->thumbnail($user_avatar)
                ->image($user->rank->image_url)
                ->author(DiscordEmbedAuthor::make($user->ident.' - '.$user->name_private)
                    ->url(route('frontend.profile.show', [$user->id])))
                ->timestamp(now())
        );
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     */
    public function toArray($notifiable): array
    {
        return [
            'user_id' => $this->user->id,
        ];
    }
}
