<?php

namespace App\Notifications\Messages;

use App\Contracts\Notification;
use App\Models\User;
use App\Notifications\Channels\MailChannel;
use Illuminate\Contracts\Queue\ShouldQueue;

class UserPending extends Notification implements ShouldQueue
{
    use MailChannel;

    public function __construct(
        private readonly User $user
    ) {
        parent::__construct();

        $this->setMailable(
            'Your registration is pending',
            'notifications.mail.user.pending',
            ['user' => $this->user]
        );
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     */
    public function toArray($notifiable): array
    {
        return [
            'user_id' => $this->user->id,
        ];
    }
}
