<?php

namespace App\Notifications\Messages;

use App\Contracts\Notification;
use App\Models\News;
use App\Notifications\Channels\MailChannel;
use Illuminate\Contracts\Queue\ShouldQueue;

class NewsAdded extends Notification implements ShouldQueue
{
    use MailChannel;

    public $requires_opt_in = true;

    public function __construct(
        private readonly News $news
    ) {
        parent::__construct();

        $this->setMailable(
            $news->subject,
            'notifications.mail.news.news',
            ['news' => $news]
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
            'news_id' => $this->news->id,
        ];
    }
}
