<?php

declare(strict_types=1);

namespace App\Support;

use App\Features\Assets\Enums\AssetSlot;
use App\Features\Assets\Models\Asset;
use App\Jobs\GenerateBrandingSizes;
use Filament\Support\Colors\Color;

/**
 * Resolves airline-supplied branding — name, logo, favicon, banner and brand
 * colour — with a fallback chain that terminates in the phpVMS asset that was
 * hardcoded prior to this class existing, so an install with nothing
 * configured renders unchanged.
 *
 * Images resolve from the `assets` table (slot `branding`); the name and brand
 * colour are still settings, because a name and a colour are not files.
 *
 * Holds no state: every accessor reads through `setting()` or a (slot, key)
 * lookup on every call rather than memoizing. It is bound as a container singleton
 * (`AppServiceProvider`) purely for convenient reuse of one instance, not for
 * caching — if this class ever gains a memo, that memo must be added to
 * `config/octane.php`'s `flush` array, the same way `SettingService` is.
 */
final class Branding
{
    /** Fallback brand colour: the phpVMS blue. */
    private const string DEFAULT_BRAND_COLOR = '#067ec1';

    /**
     * Asset keys in the `branding` slot. Fixed names, not admin-chosen: this
     * class and the ACARS client both look them up by name, so they are part of
     * the contract rather than data. The derivatives are `logo-32`, `logo-64`
     * and `logo-180`, written by {@see GenerateBrandingSizes}.
     */
    public const string KEY_LOGO = 'logo';

    public const string KEY_LOGO_DARK = 'logo-dark';

    public const string KEY_BANNER = 'banner';

    /**
     * URL of a `branding` asset, or null when the install has not uploaded one.
     *
     * One indexed lookup on (slot, key) per call, deliberately not memoized —
     * see the class docblock. Every accessor below terminates in a bundled
     * asset when this returns null, so an install with an empty `assets` table
     * renders exactly as it does today.
     */
    private function url(string $key): ?string
    {
        return Asset::query()
            ->slot(AssetSlot::BRANDING)
            ->where('key', $key)
            ->first()
            ?->url();
    }

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
            return $this->url(self::KEY_LOGO) ?? $default;
        }

        return $this->url(self::KEY_LOGO.'-'.$size)
            ?? $this->url(self::KEY_LOGO)
            ?? $default;
    }

    /**
     * Whether an airline logo has been uploaded. Lets a call site keep its
     * own pre-existing default asset instead of `logo()`'s bundled fallback,
     * for places where that fallback is a different asset than what used to
     * render there.
     */
    public function hasLogo(): bool
    {
        return $this->url(self::KEY_LOGO) !== null;
    }

    /**
     * Dark-mode logo URL. Falls back to the light logo when no dark logo has
     * been uploaded, so an install with nothing configured is unchanged.
     */
    public function logoDark(): string
    {
        return $this->url(self::KEY_LOGO_DARK) ?? $this->logo();
    }

    /**
     * Whether a dark-mode logo has been uploaded.
     */
    public function hasDarkLogo(): bool
    {
        return $this->url(self::KEY_LOGO_DARK) !== null;
    }

    /**
     * Favicon URL. Deliberately skips the full-size original when the 32px
     * derivative is missing — a full-size logo is a worse favicon than the
     * bundled one.
     */
    public function favicon(): string
    {
        return $this->url(self::KEY_LOGO.'-32') ?? asset('assets/img/favicon.png');
    }

    /**
     * Banner URL, or null when none has been uploaded.
     */
    public function banner(): ?string
    {
        return $this->url(self::KEY_BANNER);
    }

    /**
     * Brand colour as a hex string. Falls back to the phpVMS blue.
     */
    public function brandColor(): string
    {
        return setting('branding.brand_color', '') ?: self::DEFAULT_BRAND_COLOR;
    }

    /**
     * Every one of Filament's built-in Tailwind palettes, keyed by lowercase
     * name -- exactly {@see Color::all()}'s list, so the 26-palette count
     * this class relies on tracks Filament's own enumeration rather than a
     * copy of it.
     *
     * @return array<string, array<int, string>>
     */
    public function palettes(): array
    {
        return Color::all();
    }

    /**
     * Brand colour resolved to its 50-950 shade map, for the admin panel's
     * `primary` palette.
     *
     * `branding.brand_color` (see {@see brandColor()}) holds EITHER a
     * palette name, matched case-insensitively against {@see palettes()}, OR
     * a hex string, which generates its palette via
     * {@see Color::generatePalette()} -- the same call `AdminPanelProvider`
     * made directly before this method existed. A value that is neither
     * falls back to the default palette rather than feeding a malformed hex
     * into `generatePalette()`.
     *
     * @return array<int, string>
     */
    public function brandPalette(): array
    {
        $stored = $this->brandColor();

        if ($palette = $this->palettes()[strtolower($stored)] ?? null) {
            return $palette;
        }

        if (preg_match('/^#[0-9a-fA-F]{6}$/', $stored) === 1) {
            return Color::generatePalette($stored);
        }

        return Color::generatePalette(self::DEFAULT_BRAND_COLOR);
    }
}
