<?php

namespace App\Notifications\Messages\Broadcast;

use App\Contracts\Notification;
use App\Enums\PirepStatus;
use App\Models\Pirep;
use App\Notifications\DiscordEmbedColor;
use App\Support\Units\Time;
use Arthurpar06\DiscordNotifier\Embeds\DiscordEmbed;
use Arthurpar06\DiscordNotifier\Embeds\DiscordEmbedAuthor;
use Arthurpar06\DiscordNotifier\Messages\DiscordMessage;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Announce that a PIREP reached one of the key statuses.
 */
class PirepStatusChanged extends Notification implements ShouldQueue
{
    /**
     * Statuses that read as trouble rather than routine progress.
     */
    private const array DANGER_STATUSES = [
        PirepStatus::GRND_RTRN,
        PirepStatus::DIVERTED,
        PirepStatus::CANCELLED,
        PirepStatus::PAUSED,
        PirepStatus::EMERG_DESCENT,
    ];

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

        // Pirep Filed > success, normals > warning, non-normals > error
        $color = in_array($pirep->status, self::DANGER_STATUSES, true)
            ? DiscordEmbedColor::Error
            : DiscordEmbedColor::Warning;

        $embed = DiscordEmbed::make()
            ->color($color->value)
            ->title(__('notifications.discord.pirep_filed_status', [
                'ident' => $pirep->ident,
                'verb'  => __('notifications.discord.status.'.$pirep->status->value),
            ]))
            ->description($pirep->user->discord_id
                ? __('notifications.discord.flight_by', ['mention' => '<@'.$pirep->user->discord_id.'>'])
                : null)
            ->thumbnail($user_avatar)
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

        if ($pirep->landing_rate) {
            $fields[__('notifications.discord.fields.landing_rate')] = $pirep->landing_rate.'ft/min';
        }

        // Show the distance, but include the planned distance if it's been set
        $distance = [];
        if ($pirep->distance) {
            $distance[] = $pirep->distance->local(2);
        }

        if ($pirep->planned_distance) {
            $distance[] = $pirep->planned_distance->local(2);
        }

        if ($distance !== []) {
            $fields[__('notifications.discord.fields.distance')] = implode('/', $distance).' '.setting('units.distance');
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
