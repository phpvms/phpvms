<?php

declare(strict_types=1);

namespace App\Settings;

use Override;
use Spatie\LaravelSettings\Settings;

/**
 * hCaptcha protection of the public forms.
 *
 * Labels and descriptions: `resources/lang/en/settings.php` under `captcha`.
 */
final class CaptchaSettings extends Settings
{
    public bool $enabled = false;

    public string $site_key = '';

    public string $secret_key = '';

    #[Override]
    public static function group(): string
    {
        return 'captcha';
    }

    /**
     * @return list<string>
     */
    #[Override]
    public static function encrypted(): array
    {
        return ['secret_key'];
    }
}
