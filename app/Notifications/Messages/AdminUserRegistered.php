<?php

namespace App\Notifications\Messages;

use App\Contracts\Notification;
use App\Models\User;
use App\Notifications\Channels\MailChannel;
use Illuminate\Contracts\Queue\ShouldQueue;

class AdminUserRegistered extends Notification implements ShouldQueue
{
    use MailChannel;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private readonly User $user
    ) {
        parent::__construct();

        $this->setMailable(
            'A new user registered',
            'notifications.mail.admin.user.registered',
            ['user' => $user]
        );
    }

    /**
     * @return string[]
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toArray($notifiable): array
    {
        return [
            'user_id' => $this->user->id,
        ];
    }
}
