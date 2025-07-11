<?php

namespace App\Notifications\Messages\Broadcast;

use App\Contracts\Notification;
use App\Models\News;
use App\Notifications\Channels\Discord\DiscordMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use League\HTMLToMarkdown\HtmlConverter;

class NewsAdded extends Notification implements ShouldQueue
{
    public function __construct(private readonly \App\Models\News $news)
    {
        parent::__construct();
    }

    public function via($notifiable): array
    {
        return ['discord_webhook'];
    }

    /**
     * @param News $news
     */
    public function toDiscordChannel($news): ?DiscordMessage
    {
        $dm = new DiscordMessage();
        $markdown = (new HtmlConverter(['header_style' => 'atx']))->convert($news->body);

        return $dm->webhook(setting('notifications.discord_public_webhook_url'))
            ->success()
            ->title('News: '.$news->subject)
            ->author([
                'name'     => $news->user->ident.' - '.$news->user->name_private,
                'url'      => '',
                'icon_url' => $news->user->resolveAvatarUrl(),
            ])
            ->description($markdown);
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     */
    public function toArray($notifiable): array
    {
        return [
            'news_id' => $this->news->id,
        ];
    }
}
