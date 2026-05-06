<?php

namespace App\Notifications\Channels\Discord;

use App\Contracts\Notification;
use App\Support\HttpClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Message;
use Illuminate\Support\Facades\Log;

class DiscordWebhook
{
    public function __construct(private readonly HttpClient $httpClient) {}

    public function send($notifiable, Notification $notification): void
    {
        if (!method_exists($notification, 'toDiscordChannel')) {
            return;
        }

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
        } catch (RequestException $requestException) {
            $request = Message::toString($requestException->getRequest());
            $response = Message::toString($requestException->getResponse());
            Log::error('Error sending Discord notification: request: '.$requestException->getMessage().', '.$request);
            Log::error('Error sending Discord notification: response: '.$response);
        }
    }
}
