<?php

namespace App\Notifications\Messages;

use App\Contracts\Notification;
use App\Models\Pirep;
use App\Notifications\Channels\MailChannel;
use Illuminate\Contracts\Queue\ShouldQueue;

class PirepFiled extends Notification implements ShouldQueue
{
    use MailChannel;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private readonly Pirep $pirep
    ) {
        parent::__construct();

        $this->setMailable(
            'New PIREP Submitted',
            'notifications.mail.admin.pirep.submitted',
            ['pirep' => $this->pirep]
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
            'pirep_id' => $this->pirep->id,
            'user_id'  => $this->pirep->user_id,
        ];
    }
}
