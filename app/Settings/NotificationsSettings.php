<?php

declare(strict_types=1);

namespace App\Settings;

use Override;
use Spatie\LaravelSettings\Settings;

/**
 * Which events send mails and Discord messages, and where.
 *
 * Labels and descriptions: `resources/lang/en/settings.php` under `notifications`.
 */
final class NotificationsSettings extends Settings
{
    public string $discord_public_route = '';

    public string $discord_private_route = '';

    public bool $discord_pirep_status = true;

    public bool $mail_pirep_admin = true;

    public bool $mail_pirep_user_ack = true;

    public bool $mail_pirep_user_rej = true;

    public bool $mail_news = true;

    public bool $discord_award_awarded = true;

    public bool $discord_user_rank_changed = true;

    public bool $discord_pirep_diverted = true;

    public bool $discord_pirep_filed = true;

    #[Override]
    public static function group(): string
    {
        return 'notifications';
    }
}
