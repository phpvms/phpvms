<?php

namespace App\Notifications\Messages;

use App\Contracts\Notification;
use App\Models\User;
use App\Notifications\Channels\MailChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class UserRejected extends Notification implements ShouldQueue
{
    use MailChannel;
    use Queueable;

    public function __construct(
        private readonly User $user
    ) {
        parent::__construct();

        $this->setMailable(
            'Your registration has been denied',
            'notifications.mail.user.rejected',
            ['user' => $this->user]
        );
    }

    public function via($notifiable)
    {
        return ['mail'];
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
