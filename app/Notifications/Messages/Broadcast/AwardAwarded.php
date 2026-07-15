<?php

namespace App\Notifications\Messages\Broadcast;

use App\Contracts\Notification;
use App\Models\UserAward;
use App\Notifications\Concerns\BuildsDiscordEmbeds;
use App\Notifications\DiscordEmbedColor;
use Arthurpar06\DiscordNotifier\Embeds\DiscordEmbed;
use Arthurpar06\DiscordNotifier\Embeds\DiscordEmbedAuthor;
use Arthurpar06\DiscordNotifier\Messages\DiscordMessage;
use Illuminate\Contracts\Queue\ShouldQueue;

class AwardAwarded extends Notification implements ShouldQueue
{
    use BuildsDiscordEmbeds;

    /**
     * Create a new notification instance.
     */
    public function __construct(private readonly UserAward $userAward) {}

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
        $award = $this->userAward->award;
        $user = $this->userAward->user;

        $user_avatar = $this->discordAvatarUrl($user);

        return DiscordMessage::make()->embed(
            DiscordEmbed::make()
                ->color(DiscordEmbedColor::Success->value)
                ->title(__('notifications.discord.award_received', ['award' => $award->name]))
                ->description($user->discord_id
                    ? __('notifications.discord.awarded_to', ['mention' => '<@'.$user->discord_id.'>'])
                    : null)
                ->thumbnail($user_avatar)
                ->image($award->image_url)
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
            'user_id' => $this->userAward->user_id,
        ];
    }
}
