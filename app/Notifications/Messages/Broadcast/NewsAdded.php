<?php

namespace App\Notifications\Messages\Broadcast;

use App\Contracts\Notification;
use App\Models\News;
use App\Notifications\DiscordEmbedColor;
use Arthurpar06\DiscordNotifier\Components\Button;
use Arthurpar06\DiscordNotifier\Embeds\DiscordEmbed;
use Arthurpar06\DiscordNotifier\Embeds\DiscordEmbedAuthor;
use Arthurpar06\DiscordNotifier\Messages\DiscordMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;
use League\HTMLToMarkdown\HtmlConverter;

class NewsAdded extends Notification implements ShouldQueue
{
    public function __construct(private readonly News $news) {}

    public function via($notifiable): array
    {
        return ['discord'];
    }

    /**
     * Send a Discord notification. The destination comes from the notifiable,
     * so this only builds content.
     */
    public function toDiscord($notifiable): DiscordMessage
    {
        $news = $this->news;
        $markdown = new HtmlConverter(['header_style' => 'atx'])->convert($news->body);

        return DiscordMessage::make()
            ->embed(
                DiscordEmbed::make()
                    ->color(DiscordEmbedColor::Success->value)
                    ->title(__('notifications.discord.news', ['subject' => $news->subject]))
                    ->author(DiscordEmbedAuthor::make($news->user->ident.' - '.$news->user->name_private)
                        ->iconUrl($news->user->resolveAvatarUrl()))
                    // Str::limit appends its ellipsis after trimming, so the
                    // budget has to leave room for it or a long article lands
                    // one character over Discord's limit.
                    ->description(Str::limit($markdown, DiscordEmbed::MAX_DESCRIPTION - 1, '…'))
                    ->timestamp(now())
            )
            // The news widget on the dashboard is the only place the full body
            // renders; phpVMS has no per-article page to deep-link to.
            ->button(Button::link(
                route('frontend.dashboard.index'),
                __('notifications.discord.read_more'),
            ));
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
