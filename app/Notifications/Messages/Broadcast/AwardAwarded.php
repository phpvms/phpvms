<?php

namespace App\Notifications\Messages\Broadcast;

use App\Contracts\Notification;
use App\Models\Award;
use App\Models\User;
use App\Models\UserAward;
use App\Notifications\Channels\Discord\DiscordMessage;
use Illuminate\Contracts\Queue\ShouldQueue;

class AwardAwarded extends Notification implements ShouldQueue
{
    private $userAward;

    /**
     * Create a new notification instance.
     *
     * @param \App\Models\Pirep $pirep
     */
    public function __construct(UserAward $userAward)
    {
        parent::__construct();

        $this->userAward = $userAward;
    }

    public function via($notifiable)
    {
        return ['discord_webhook'];
    }

    /**
     * Send a Discord notification.
     *
     * @param Pirep $pirep
     * @param mixed $userAward
     */
    public function toDiscordChannel($userAward): ?DiscordMessage
    {
        $award = Award::where('id', $userAward->award_id)->first();

        $user = User::where('id', $userAward->user_id)->first();

        $title = 'Received award '.$award->name;
        // $fields = $this->createFields($user);

        // User avatar, somehow $pirep->user->resolveAvatarUrl() is not being accepted by Discord as thumbnail
        $user_avatar = !empty($user->avatar)
            ? $user->avatar->url
            : $user->gravatar(256);

        $dm = new DiscordMessage();

        return $dm
            ->webhook(setting('notifications.discord_public_webhook_url'))
            ->success()
            ->title($title)
            ->description(
                $user->discord_id
                    ? 'Awarded by <@'.$user->discord_id.'>'
                    : ''
            )
            ->thumbnail(['url' => $user_avatar])
            ->image(['url' => $award->image_url])
            ->author([
                'name' => $user->ident.' - '.$user->name_private,
                'url'  => route('frontend.profile.show', [$user->id]),
            ]);
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
            'user_id' => $this->userAward->user_id,
        ];
    }
}
