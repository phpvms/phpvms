<?php

namespace App\Notifications\Messages\Broadcast;

use App\Contracts\Notification;
use App\Models\Pirep;
use App\Notifications\DiscordEmbedColor;
use App\Support\Units\Time;
use Arthurpar06\DiscordNotifier\Embeds\DiscordEmbed;
use Arthurpar06\DiscordNotifier\Embeds\DiscordEmbedAuthor;
use Arthurpar06\DiscordNotifier\Messages\DiscordMessage;
use Illuminate\Contracts\Queue\ShouldQueue;

class PirepFiled extends Notification implements ShouldQueue
{
    /**
     * Create a new notification instance.
     */
    public function __construct(private readonly Pirep $pirep) {}

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
        $pirep = $this->pirep;

        // User avatar, somehow $pirep->user->resolveAvatarUrl() is not being accepted by Discord as thumbnail
        $user_avatar = empty($pirep->user->avatar) ? $pirep->user->gravatar(256) : $pirep->user->avatar->url;

        $embed = DiscordEmbed::make()
            ->color(DiscordEmbedColor::Success->value)
            ->title(__('notifications.discord.pirep_filed', ['ident' => $pirep->ident]))
            ->description($pirep->user->discord_id
                ? __('notifications.discord.flight_by', ['mention' => '<@'.$pirep->user->discord_id.'>'])
                : null)
            ->thumbnail($user_avatar)
            ->image($pirep->airline->logo)
            ->author(DiscordEmbedAuthor::make($pirep->user->ident.' - '.$pirep->user->name_private)
                ->url(route('frontend.profile.show', [$pirep->user_id])))
            ->timestamp(now());

        foreach ($this->createFields($pirep) as $name => $value) {
            // Names stay bolded and inline, as the previous embed builder forced.
            $embed->field('**'.$name.'**', (string) $value, true);
        }

        return DiscordMessage::make()->embed($embed);
    }

    /**
     * @return array<string, string>
     */
    private function createFields(Pirep $pirep): array
    {
        $fields = [
            __('notifications.discord.fields.dep_airport') => $pirep->dpt_airport_id,
            __('notifications.discord.fields.arr_airport') => $pirep->arr_airport_id,
            __('notifications.discord.fields.equipment')   => $pirep->aircraft->ident,
            __('notifications.discord.fields.flight_time') => Time::minutesToTimeString($pirep->flight_time),
        ];

        if ($pirep->distance) {
            $fields[__('notifications.discord.fields.distance')] = $pirep->distance->local(2).' '.setting('units.distance');
        }

        return $fields;
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     */
    public function toArray($notifiable): array
    {
        return [
            'pirep_id' => $this->pirep->id,
            'user_id'  => $this->pirep->user_id,
        ];
    }
}
