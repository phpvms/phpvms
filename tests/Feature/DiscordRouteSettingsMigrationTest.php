<?php

declare(strict_types=1);

use App\Models\Setting;

function discordRouteMigration(): object
{
    return require base_path('database/migrations_data/2026_07_14_000001_discord_route_settings.php');
}

function putSetting(string $key, string $value, string $name): void
{
    $model = new Setting([
        'key'         => $key,
        'name'        => $name,
        'value'       => $value,
        'group'       => 'notifications',
        'type'        => 'text',
        'options'     => '',
        'description' => '',
    ]);
    $model->id = Setting::formatKey($key);
    $model->default = '';
    $model->offset = 3;
    $model->order = 3;
    $model->save();
}

/**
 * Drop the new keys, leaving the shape an install has *before* SettingsSeeder
 * has ever run with them. Note this is NOT the upgrade order — Updater runs
 * syncAllSeeds() before the data migrations, so the new keys normally exist and
 * are empty by the time the migration runs. See the seeded-first test below.
 */
function dropRouteSettings(): void
{
    Setting::query()->whereIn('id', [
        Setting::formatKey('notifications.discord_public_route'),
        Setting::formatKey('notifications.discord_private_route'),
    ])->delete();
}

beforeEach(function (): void {
    dropRouteSettings();
});

test('the seeder having already created the new key does not cost the admin their value', function (): void {
    // The real upgrade order: Updater.php runs syncAllSeeds() (which inserts
    // notifications.discord_public_route with an empty value) and only then runs
    // the data migrations. Deleting the source without carrying its value across
    // would silently unconfigure Discord on every install that had it working.
    putSetting('notifications.discord_public_route', '', 'Discord Public Route');
    putSetting('notifications.discord_public_webhook_url', 'https://discord.com/api/webhooks/1/abc', 'Discord Public Webhook URL');

    discordRouteMigration()->up();

    expect(setting('notifications.discord_public_route'))->toBe('https://discord.com/api/webhooks/1/abc')
        ->and(Setting::where('id', Setting::formatKey('notifications.discord_public_webhook_url'))->exists())->toBeFalse();
});

test('a value already set on the new key wins over the old one', function (): void {
    putSetting('notifications.discord_public_route', '123456789012345678', 'Discord Public Route');
    putSetting('notifications.discord_public_webhook_url', 'https://discord.com/api/webhooks/1/stale', 'Discord Public Webhook URL');

    discordRouteMigration()->up();

    expect(setting('notifications.discord_public_route'))->toBe('123456789012345678');
});

test('an empty old value does not overwrite a configured new key', function (): void {
    putSetting('notifications.discord_public_route', '123456789012345678', 'Discord Public Route');
    putSetting('notifications.discord_public_webhook_url', '', 'Discord Public Webhook URL');

    discordRouteMigration()->up();

    expect(setting('notifications.discord_public_route'))->toBe('123456789012345678');
});

test('an install with a configured webhook keeps its value', function (): void {
    putSetting('notifications.discord_public_webhook_url', 'https://discord.com/api/webhooks/1/abc', 'Discord Public Webhook URL');

    discordRouteMigration()->up();

    $new = Setting::where('id', Setting::formatKey('notifications.discord_public_route'))->first();

    expect($new)->not->toBeNull()
        ->and($new->value)->toBe('https://discord.com/api/webhooks/1/abc')
        // The row keeps its place in the notifications group.
        ->and($new->offset)->toBe(3)
        ->and(Setting::where('id', Setting::formatKey('notifications.discord_public_webhook_url'))->exists())->toBeFalse();
});

test('both webhook settings are carried across', function (): void {
    putSetting('notifications.discord_public_webhook_url', 'https://discord.com/api/webhooks/1/public', 'Discord Public Webhook URL');
    putSetting('notifications.discord_private_webhook_url', 'https://discord.com/api/webhooks/2/staff', 'Discord Private Webhook URL');

    discordRouteMigration()->up();

    expect(setting('notifications.discord_public_route'))->toBe('https://discord.com/api/webhooks/1/public')
        ->and(setting('notifications.discord_private_route'))->toBe('https://discord.com/api/webhooks/2/staff');
});

test('an unconfigured install ends up with empty routes', function (): void {
    putSetting('notifications.discord_public_webhook_url', '', 'Discord Public Webhook URL');

    discordRouteMigration()->up();

    $new = Setting::where('id', Setting::formatKey('notifications.discord_public_route'))->first();

    expect($new)->not->toBeNull()
        ->and($new->value)->toBe('');
});

test('the migration is idempotent', function (): void {
    putSetting('notifications.discord_public_webhook_url', 'https://discord.com/api/webhooks/1/abc', 'Discord Public Webhook URL');

    discordRouteMigration()->up();
    discordRouteMigration()->up();

    expect(Setting::where('id', Setting::formatKey('notifications.discord_public_route'))->count())->toBe(1)
        ->and(setting('notifications.discord_public_route'))->toBe('https://discord.com/api/webhooks/1/abc');
});

test('a re-run does not clobber a value an admin has since changed', function (): void {
    putSetting('notifications.discord_public_webhook_url', 'https://discord.com/api/webhooks/1/abc', 'Discord Public Webhook URL');

    discordRouteMigration()->up();

    // Admin switches the public route over to a bot channel.
    Setting::where('id', Setting::formatKey('notifications.discord_public_route'))->update(['value' => '123456789012345678']);

    discordRouteMigration()->up();

    expect(setting('notifications.discord_public_route'))->toBe('123456789012345678');
});

test('down restores the webhook keys with their values', function (): void {
    putSetting('notifications.discord_public_webhook_url', 'https://discord.com/api/webhooks/1/abc', 'Discord Public Webhook URL');

    $migration = discordRouteMigration();
    $migration->up();
    $migration->down();

    expect(setting('notifications.discord_public_webhook_url'))->toBe('https://discord.com/api/webhooks/1/abc')
        ->and(Setting::where('id', Setting::formatKey('notifications.discord_public_route'))->exists())->toBeFalse();
});
