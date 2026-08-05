<?php

declare(strict_types=1);

namespace App\Notifications\Messages;

use App\Contracts\Notification;
use App\Notifications\Channels\MailChannel;
use Illuminate\Contracts\Queue\ShouldQueue;

class PilotIdRangeUtilization extends Notification implements ShouldQueue
{
    use MailChannel;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private readonly int $taken,
        private readonly int $total,
        private readonly float $utilization,
        private readonly int $rangeStart,
        private readonly int $rangeEnd,
    ) {
        $this->setMailable(
            'Pilot ID range utilization at '.round($utilization).'%',
            'notifications.mail.admin.pilots.id_range_utilization',
            [
                'taken'       => $taken,
                'total'       => $total,
                'utilization' => $utilization,
                'range_start' => $rangeStart,
                'range_end'   => $rangeEnd,
            ]
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
            'taken'       => $this->taken,
            'total'       => $this->total,
            'utilization' => $this->utilization,
        ];
    }
}
