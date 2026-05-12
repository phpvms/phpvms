<?php

namespace App\Notifications\Messages\Broadcast;

use App\Contracts\Notification;
use App\Enums\PirepStatus;
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
    // TODO: Int'l languages for these
    protected static $verbs = [
        'INI' => 'is initialized',
        'SCH' => 'is scheduled',
        'BST' => 'is boarding',
        'RDT' => 'is ready for start',
        'PBT' => 'is pushing back',
        'OFB' => 'has departed',
        'DIR' => 'is ready for de-icing',
        'DIC' => 'is de-icing',
        'GRT' => 'on ground return',
        'TXI' => 'is taxiing',
        'TOF' => 'has taken off',
        'ICL' => 'in initial climb',
        'TKO' => 'is enroute',
        'ENR' => 'is enroute',
        'DV'  => 'has diverted',
        'TEN' => 'on approach',
        'APR' => 'on approach',
        'FIN' => 'on final approach',
        'LDG' => 'is landing',
        'LAN' => 'has landed',
        'ONB' => 'has arrived',
        'ARR' => 'has arrived',
        'DX'  => 'is cancelled',
        'PSD' => 'is paused',
        'EMG' => 'in emergency descent',
    ];

    /**
     * Create a new notification instance.
     */
    public function __construct(private readonly Pirep $pirep)
    {
        parent::__construct();
    }

    public function via($notifiable): array
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

        $title = 'Flight '.$pirep->ident.' '.self::$verbs[$pirep->status->value];
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
