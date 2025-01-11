<?php

namespace App\Notifications\Messages\Broadcast;

use App\Contracts\Notification;
use App\Models\User;
use App\Notifications\Channels\Discord\DiscordMessage;
use App\Notifications\Channels\MailChannel;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Send a message to a Discord channel that a user was registered
 */
class UserRegistered extends Notification implements ShouldQueue
{
    use MailChannel;

    private $user;

    /**
     * Create a new notification instance.
     */
    public function __construct(User $user)
    {
        parent::__construct();

        $this->user = $user;
    }

    /**
     * @param mixed $notifiable
     *
     * @return string[]
     */
    public function via($notifiable)
    {
        return ['discord_webhook'];
    }

    /**
     * Send a Discord notification
     *
     * @param mixed $notifiable
     */
    public function toDiscordChannel($notifiable): ?DiscordMessage
    {
        $dm = new DiscordMessage();

        return $dm->webhook(setting('notifications.discord_private_webhook_url'))
            ->success()
            ->title('New User Registered: '.$this->user->ident)
            ->author([
                'name'     => $this->user->ident.' - '.$this->user->name_private,
                'url'      => '',
                'icon_url' => $this->user->resolveAvatarUrl(),
            ])
            ->fields([]);
    }

    /**
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
