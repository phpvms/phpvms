<?php

namespace App\Notifications\Messages\Broadcast;

use App\Contracts\Notification;
use App\Models\Pirep;
use App\Notifications\Channels\Discord\DiscordMessage;

class PirepDiverted extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(private readonly Pirep $pirep)
    {
        parent::__construct();
    }

    public function via($notifiable): array
    {
        return ['discord_webhook'];
    }

    /**
     * Send a Discord notification
     */
    public function toDiscordChannel(\App\Models\Pirep $pirep): ?DiscordMessage
    {
        $title = 'Flight '.$pirep->ident.' Diverted';
        $fields = $this->createFields($pirep);

        // User avatar, somehow $pirep->user->resolveAvatarUrl() is not being accepted by Discord as thumbnail
        $user_avatar = $pirep->user->avatar?->url ?? $pirep->user->gravatar(256);

        $dm = new DiscordMessage();

        return $dm->webhook(setting('notifications.discord_public_webhook_url'))
            ->success()
            ->title($title)
            ->thumbnail(['url' => $user_avatar])
            ->author([
                'name' => 'Pilot In Command: '.$pirep->user->ident.' - '.$pirep->user->name_private,
                'url'  => route('frontend.profile.show', [$pirep->user_id]),
            ])
            ->fields($fields);
    }

    public function createFields(Pirep $pirep): array
    {
        $diversion_apt = $pirep->fields->firstWhere('slug', 'diversion-airport')->value;
        $diversion_reason = abs($pirep->landing_rate) > 1500 ? 'Crashed Near '.$diversion_apt : 'Operational';

        return [
            '__Flight #__'  => $pirep->ident,
            '__Orig__'      => $pirep->dpt_airport_id,
            '__Dest__'      => $pirep->arr_airport_id,
            '__Equipment__' => $pirep->aircraft?->ident ?? 'Not Reported',
            '__Diverted__'  => $diversion_apt,
            '__Reason__'    => $diversion_reason,
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
