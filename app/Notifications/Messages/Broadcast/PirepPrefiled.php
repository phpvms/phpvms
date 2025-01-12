<?php

namespace App\Notifications\Messages\Broadcast;

use App\Contracts\Notification;
use App\Models\Pirep;
use App\Notifications\Channels\Discord\DiscordMessage;
use App\Notifications\Channels\Discord\DiscordWebhook;
use App\Support\Units\Time;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Send the PIREP accepted message to a particular user, can also be sent to Discord.
 */
class PirepPrefiled extends Notification implements ShouldQueue
{
    private $pirep;

    /**
     * Create a new notification instance.
     */
    public function __construct(Pirep $pirep)
    {
        parent::__construct();
        $this->pirep = $pirep;
    }

    public function via($notifiable)
    {
        return [DiscordWebhook::class];
    }

    /**
     * Send a Discord notification.
     *
     * @param Pirep $pirep
     */
    public function toDiscordChannel($pirep): ?DiscordMessage
    {
        if (empty(setting('notifications.discord_public_webhook_url'))) {
            return null;
        }

        $title = 'Flight '.$pirep->ident.' Prefiled';
        $fields = $this->createFields($pirep);

        $dm = new DiscordMessage();

        return $dm->webhook(setting('notifications.discord_public_webhook_url'))
            ->success()
            ->title($title)
            ->description(
                $pirep->user->discord_id ? 'Flight by <@'.$pirep->user->discord_id.'>' : ''
            )
            ->url(route('frontend.pireps.show', [$pirep->id]))
            ->author([
                'name'     => $pirep->user->ident.' - '.$pirep->user->name_private,
                'url'      => route('frontend.profile.show', [$pirep->user_id]),
                'icon_url' => $pirep->user->resolveAvatarUrl(),
            ])
            ->fields($fields);
    }

    public function createFields(Pirep $pirep): array
    {
        $fields = [
            'Flight'                => $pirep->ident,
            'Departure Airport'     => $pirep->dpt_airport_id,
            'Arrival Airport'       => $pirep->arr_airport_id,
            'Equipment'             => $pirep->aircraft->ident,
            'Flight Time (Planned)' => Time::minutesToTimeString($pirep->planned_flight_time),
        ];

        if ($pirep->planned_distance) {
            $fields['Distance'] = $pirep->planned_distance->local(2).' '.setting('units.distance');
        }

        return $fields;
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'pirep_id' => $this->pirep->id,
            'user_id'  => $this->pirep->user_id,
        ];
    }
}
