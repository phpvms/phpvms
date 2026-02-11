<?php

namespace App\Notifications\Messages\Broadcast;

use App\Contracts\Notification;
use App\Models\Enums\PirepStatus;
use App\Models\Pirep;
use App\Notifications\Channels\Discord\DiscordMessage;
use App\Notifications\Channels\Discord\DiscordWebhook;
use App\Support\Units\Time;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Send the PIREP accepted message to a particular user, can also be sent to Discord
 */
class PirepStatusChanged extends Notification implements ShouldQueue
{
    private $pirep;

    // TODO: Int'l languages for these
    protected static $verbs = [
        PirepStatus::INITIATED     => 'is initialized',
        PirepStatus::SCHEDULED     => 'is scheduled',
        PirepStatus::BOARDING      => 'is boarding',
        PirepStatus::RDY_START     => 'is ready for start',
        PirepStatus::PUSHBACK_TOW  => 'is pushing back',
        PirepStatus::DEPARTED      => 'has departed',
        PirepStatus::RDY_DEICE     => 'is ready for de-icing',
        PirepStatus::STRT_DEICE    => 'is de-icing',
        PirepStatus::GRND_RTRN     => 'on ground return',
        PirepStatus::TAXI          => 'is taxiing',
        PirepStatus::TAKEOFF       => 'has taken off',
        PirepStatus::INIT_CLIM     => 'in initial climb',
        PirepStatus::AIRBORNE      => 'is enroute',
        PirepStatus::ENROUTE       => 'is enroute',
        PirepStatus::DIVERTED      => 'has diverted',
        PirepStatus::APPROACH      => 'on approach',
        PirepStatus::APPROACH_ICAO => 'on approach',
        PirepStatus::ON_FINAL      => 'on final approach',
        PirepStatus::LANDING       => 'is landing',
        PirepStatus::LANDED        => 'has landed',
        PirepStatus::ON_BLOCK      => 'has arrived',
        PirepStatus::ARRIVED       => 'has arrived',
        PirepStatus::CANCELLED     => 'is cancelled',
        PirepStatus::PAUSED        => 'is paused',
        PirepStatus::EMERG_DESCENT => 'in emergency descent',
    ];

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
     * Send a Discord notification
     *
     * @param Pirep $pirep
     */
    public function toDiscordChannel($pirep): ?DiscordMessage
    {
        if (empty(setting('notifications.discord_public_webhook_url'))) {
            return null;
        }

        $title = 'Flight '.$pirep->ident.' '.self::$verbs[$pirep->status];
        $fields = $this->createFields($pirep);

        // User avatar, somehow $pirep->user->resolveAvatarUrl() is not being accepted by Discord as thumbnail
        $user_avatar = empty($pirep->user->avatar) ? $pirep->user->gravatar(256) : $pirep->user->avatar->url;

        // Proper coloring for the messages
        // Pirep Filed > success, normals > warning, non-normals > error
        $danger_types = [
            PirepStatus::GRND_RTRN,
            PirepStatus::DIVERTED,
            PirepStatus::CANCELLED,
            PirepStatus::PAUSED,
            PirepStatus::EMERG_DESCENT,
        ];

        $color = in_array($pirep->status, $danger_types, true) ? 'ED2939' : 'FD6A02';

        $dm = new DiscordMessage();

        return $dm->webhook(setting('notifications.discord_public_webhook_url'))
            ->color($color)
            ->title($title)
            ->description($pirep->user->discord_id ? 'Flight by <@'.$pirep->user->discord_id.'>' : '')
            ->thumbnail(['url' => $user_avatar])
            ->author([
                'name' => $pirep->user->ident.' - '.$pirep->user->name_private,
                'url'  => route('frontend.profile.show', [$pirep->user_id]),
            ])
            ->fields($fields);
    }

    public function createFields(Pirep $pirep): array
    {
        $fields = [
            'Dep.Airport' => $pirep->dpt_airport_id,
            'Arr.Airport' => $pirep->arr_airport_id,
            'Equipment'   => $pirep->aircraft->ident,
            'Flight Time' => Time::minutesToTimeString($pirep->flight_time),
        ];

        if ($pirep->landing_rate) {
            $fields['Landing Rate'] = $pirep->landing_rate.'ft/min';
        }

        // Show the distance, but include the planned distance if it's been set
        $fields['Distance'] = [];
        if ($pirep->distance) {
            $fields['Distance'][] = $pirep->distance->local(2);
        }

        if ($pirep->planned_distance) {
            $fields['Distance'][] = $pirep->planned_distance->local(2);
        }

        if ($fields['Distance'] !== []) {
            $fields['Distance'] = implode('/', $fields['Distance']);
            $fields['Distance'] .= ' '.setting('units.distance');
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
