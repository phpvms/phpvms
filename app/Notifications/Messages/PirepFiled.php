<?php

namespace App\Notifications\Messages;

use App\Contracts\Notification;
use App\Mail\AdminPirepSubmitted;
use App\Models\Pirep;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;

class PirepFiled extends Notification implements ShouldQueue
{
    public function __construct(
        private readonly Pirep $pirep
    ) {
        parent::__construct();
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable): Mailable
    {
        $email = new AdminPirepSubmitted($this->pirep);
        $email->to($notifiable->email);

        return $email;
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
