<?php

declare(strict_types=1);

namespace App\Notifications\Messages;

use App\Contracts\Notification;
use App\Models\User;
use App\Notifications\Channels\MailChannel;
use Illuminate\Contracts\Queue\ShouldQueue;

class UserRegistered extends Notification implements ShouldQueue
{
    use MailChannel;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private readonly User $user
    ) {

        $this->setMailable(
            'Welcome to '.config('app.name').'!',
            'notifications.mail.user.registered',
            ['user' => $this->user]
        );
    }

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
