<?php

declare(strict_types=1);

namespace App\Notifications\Notifiables;

use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Notifications\Notifiable;

/**
 * Member-facing announcements, broadcast to a channel rather than to a person.
 *
 * Notification::send([app(PublicBroadcast::class)], $notification);
 */
class PublicBroadcast implements HasLocalePreference
{
    use Notifiable;

    /**
     * A channel is read by everyone, so an announcement is rendered in the
     * site's own language rather than the language of whoever happened to
     * trigger it. Without this, SetActiveLanguage would leave the triggering
     * visitor's locale in place and Laravel would render the announcement in it.
     *
     * Not config('app.locale'): App::setLocale() overwrites that key with the
     * visitor's language. Falls back rather than returning null, so an install
     * whose config cache predates phpvms.default_locale announces in the
     * fallback language instead of failing every queued notification.
     */
    public function preferredLocale(): string
    {
        return config('phpvms.default_locale') ?? config('app.fallback_locale', 'en');
    }

    /**
     * There is one public broadcast audience, so the class is the identity.
     * Laravel indexes notifiables by class and key (see NotificationFake).
     */
    public function getKey(): string
    {
        return 'public';
    }

    /**
     * A webhook URL or a channel id — the notifier resolves the transport from
     * the value's shape. Null when Discord is not configured, which the channel
     * treats as "nowhere to send" and skips.
     */
    public function routeNotificationForDiscord(): ?string
    {
        return setting('notifications.discord_public_route') ?: null;
    }
}
