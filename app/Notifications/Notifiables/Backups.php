<?php

namespace App\Notifications\Notifiables;

use Illuminate\Notifications\Notifiable;

class Backups
{
    use Notifiable;

    public function routeNotificationForMail()
    {
        return setting('general.admin_email');
    }

    public function routeNotificationForSlack()
    {
        return config('backup.notifications.slack.webhook_url');
    }

    public function getKey(): int
    {
        return 1;
    }
}
