<?php

namespace App\Notifications\Messages\Broadcast;

use App\Contracts\Notification;
use App\Models\Pirep;
use App\Notifications\Concerns\BuildsDiscordEmbeds;
use App\Notifications\DiscordEmbedColor;
use Arthurpar06\DiscordNotifier\Embeds\DiscordEmbed;
use Arthurpar06\DiscordNotifier\Embeds\DiscordEmbedAuthor;
use Arthurpar06\DiscordNotifier\Messages\DiscordMessage;

class PirepDiverted extends Notification
{
    use BuildsDiscordEmbeds;

    /**
     * A landing rate beyond this reads as a crash rather than an operational
     * diversion.
     */
    private const int CRASH_LANDING_RATE = 1500;

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

        $user_avatar = $this->discordAvatarUrl($pirep->user);

        $embed = DiscordEmbed::make()
            ->color(DiscordEmbedColor::Error->value)
            ->title(__('notifications.discord.pirep_diverted', ['ident' => $pirep->ident]))
            ->thumbnail($user_avatar)
            ->author(DiscordEmbedAuthor::make(__('notifications.discord.diverted.pilot_in_command', [
                'pilot' => $pirep->user->ident.' - '.$pirep->user->name_private,
            ]))->url(route('frontend.profile.show', [$pirep->user_id])))
            ->timestamp(now());

        $this->addDiscordFields($embed, $this->createFields($pirep));

        return DiscordMessage::make()->embed($embed);
    }

    /**
     * @return array<string, string>
     */
    private function createFields(Pirep $pirep): array
    {
        // No ?-> needed: ?? already suppresses reading value off a missing field.
        $diversion_apt = $pirep->fields->firstWhere('slug', 'diversion-airport')->value
            ?? __('notifications.discord.diverted.not_reported');

        $diversion_reason = abs((float) $pirep->landing_rate) > self::CRASH_LANDING_RATE
            ? __('notifications.discord.diverted.reason_crashed', ['airport' => $diversion_apt])
            : __('notifications.discord.diverted.reason_operational');

        return [
            '__'.__('notifications.discord.diverted.flight_no').'__' => $pirep->ident,
            '__'.__('notifications.discord.diverted.orig').'__'      => $pirep->dpt_airport_id,
            '__'.__('notifications.discord.diverted.dest').'__'      => $pirep->arr_airport_id,
            '__'.__('notifications.discord.diverted.equipment').'__' => $pirep->aircraft->ident ?? __('notifications.discord.diverted.not_reported'),
            '__'.__('notifications.discord.diverted.diverted').'__'  => $diversion_apt,
            '__'.__('notifications.discord.diverted.reason').'__'    => $diversion_reason,
        ];
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
