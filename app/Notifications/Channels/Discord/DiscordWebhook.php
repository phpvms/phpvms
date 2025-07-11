<?php

namespace App\Notifications\Channels\Discord;

use App\Contracts\Notification;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7;
use Illuminate\Support\Facades\Log;

class DiscordWebhook
{
    public function __construct(private readonly \App\Support\HttpClient $httpClient) {}

    public function send($notifiable, Notification $notification): void
    {
        $message = $notification->toDiscordChannel($notifiable);
        if ($message === null) {
            // Log::debug('Discord notifications not configured, skipping');
            return;
        }

        $webhook_url = $message->webhook_url;
        if (empty($webhook_url)) {
            $webhook_url = setting('notifications.discord_private_webhook_url');
            if (empty($webhook_url)) {
                // Log::debug('Discord notifications not configured, skipping');
                return;
            }
        }

        try {
            $data = $message->toArray();
            $this->httpClient->post($webhook_url, $data);
        } catch (RequestException $e) {
            $request = Psr7\Message::toString($e->getRequest());
            $response = Psr7\Message::toString($e->getResponse());
            Log::error('Error sending Discord notification: request: '.$e->getMessage().', '.$request);
            Log::error('Error sending Discord notification: response: '.$response);
        }
    }
}
