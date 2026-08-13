<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Resolves airline-supplied branding — name, logo, favicon, banner and brand
 * colour — with a fallback chain that terminates in the phpVMS asset that was
 * hardcoded prior to this class existing, so an install with nothing
 * configured renders unchanged.
 *
 * Holds no state: every accessor reads through the `setting()` helper on
 * every call rather than memoizing. It is bound as a container singleton
 * (`AppServiceProvider`) purely for convenient reuse of one instance, not for
 * caching — if this class ever gains a memo, that memo must be added to
 * `config/octane.php`'s `flush` array, the same way `SettingService` is.
 */
final class Branding
{
    /** Fallback brand colour: the phpVMS blue. */
    private const string DEFAULT_BRAND_COLOR = '#067ec1';

    /**
     * Airline display name. Falls back to `config('app.name')` when
     * `general.site_name` is empty.
     */
    public function name(): string
    {
        return setting('general.site_name', '') ?: (string) config('app.name');
    }

    /**
     * Logo URL, optionally at a derivative size (32, 64 or 180).
     *
     * A sized lookup falls back to the original logo, then to the bundled
     * asset. The unsized lookup falls straight to the bundled asset.
     */
    public function logo(?int $size = null): string
    {
        $default = asset('assets/img/logo_blue.svg');

        if ($size === null) {
            return setting('branding.logo_url', '') ?: $default;
        }

        return setting("branding.logo_{$size}_url", '')
            ?: setting('branding.logo_url', '')
            ?: $default;
    }

    /**
     * Favicon URL. Deliberately skips the full-size original when the 32px
     * derivative is missing — a full-size logo is a worse favicon than the
     * bundled one.
     */
    public function favicon(): string
    {
        return setting('branding.logo_32_url', '') ?: asset('assets/img/favicon.png');
    }

    /**
     * Banner URL, or null when none has been uploaded.
     */
    public function banner(): ?string
    {
        return setting('branding.banner_url', '') ?: null;
    }

    /**
     * Brand colour as a hex string. Falls back to the phpVMS blue.
     */
    public function brandColor(): string
    {
        return setting('branding.brand_color', '') ?: self::DEFAULT_BRAND_COLOR;
    }
}
