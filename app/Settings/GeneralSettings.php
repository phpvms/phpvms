<?php

declare(strict_types=1);

namespace App\Settings;

use App\Support\Settings\SettingField;
use Carbon\CarbonImmutable;
use Override;
use Spatie\LaravelSettings\Settings;

/**
 * Site-wide settings: identity, registration and telemetry.
 *
 * Labels and descriptions: `resources/lang/en/settings.php` under `general`.
 */
final class GeneralSettings extends Settings
{
    public string $site_name = '';

    #[SettingField(hidden: true)]
    public string $theme = 'airside';

    public ?CarbonImmutable $start_date = null;

    public string $admin_email = '';

    public bool $check_prerelease_version = false;

    public bool $telemetry = true;

    public string $google_analytics_id = '';

    public bool $record_user_ip = true;

    public bool $invite_only_registrations = false;

    public bool $disable_registrations = false;

    public bool $auto_language_detection = false;

    #[Override]
    public static function group(): string
    {
        return 'general';
    }
}
