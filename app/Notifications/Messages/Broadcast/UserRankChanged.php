<?php

namespace App\Notifications\Messages\Broadcast;

use App\Contracts\Notification;
use App\Models\User;
use App\Notifications\Channels\Discord\DiscordMessage;
use Illuminate\Contracts\Queue\ShouldQueue;

class UserRankChanged extends Notification implements ShouldQueue
{
    private $user;

    /**
     * Create a new notification instance.
     *
     * @param \App\Models\Pirep $pirep
     */
    public function __construct(User $user)
    {
        parent::__construct();

        $this->user = $user;
    }

    public function via($notifiable)
    {
        return ['discord_webhook'];
    }

    /**
     * Send a Discord notification.
     *
     * @param Pirep $pirep
     * @param mixed $user
     */
    public function toDiscordChannel($user): ?DiscordMessage
    {
        $title = 'Rank changed '.$user->rank->name;
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
                    ? 'Rank changed for <@'.$user->discord_id.'>'
                    : ''
            )
            ->thumbnail(['url' => $user_avatar])
            ->image(['url' => $user->rank->image_url])
            ->author([
                'name' => $user->ident.' - '.$user->name_private,
                'url'  => route('frontend.profile.show', [$user->id]),
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     *
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'user_id' => $this->user->id,
        ];
    }
}
