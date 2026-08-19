<?php

declare(strict_types=1);

namespace App\Support;

use App\Features\Assets\AssetService;
use App\Jobs\GenerateBrandingSizes;
use App\Models\Asset;
use Filament\Support\Colors\Color;

/**
 * Owns airline-supplied branding — name, logo, favicon, banner and brand
 * colour — on both sides: {@see store()}/{@see forget()} write the `branding`
 * slot, and the accessors read it back through a fallback chain that terminates
 * in the phpVMS asset that was hardcoded prior to this class existing, so an
 * install with nothing configured renders unchanged.
 *
 * Images resolve from the `assets` table (slot `branding`); the name and brand
 * colour are still settings, because a name and a colour are not files.
 *
 * Callers hand over bytes, not decisions: which slot, whether the asset is
 * public, and whether a write needs derivatives generated are all settled here.
 * The one piece deliberately outside is the image resizing itself, which is
 * queued work and so has to be a job — {@see GenerateBrandingSizes}, dispatched
 * by `store()` and writing back through it.
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
     * Asset keys in the `branding` slot. Fixed names, not admin-chosen:
     * consumers look them up by name, so they are part of the interface rather
     * than data.
     */
    public const string KEY_LOGO = 'logo';

    public const string KEY_LOGO_DARK = 'logo-dark';

    public const string KEY_BANNER = 'banner';

    public const string KEY_FAVICON = 'favicon';

    /**
     * Sizes {@see GenerateBrandingSizes} derives from an uploaded logo, each
     * stored under its own key -- `logo-32`, `logo-64`, `logo-180`. Owned here
     * rather than by the job because the readers below look the derivatives up
     * by name, so the size list and the key spelling are one thing.
     *
     * Ascending, and {@see favicon()} depends on that: it takes the first entry
     * that resolves, which is only the smallest if this list stays sorted.
     *
     * @var list<int>
     */
    public const array LOGO_SIZES = [32, 64, 180];

    /** Asset key for a logo derivative -- `logo-32`, `logo-64`, `logo-180`. */
    public static function logoKey(int $size): string
    {
        return self::KEY_LOGO.'-'.$size;
    }

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
            ->slot(Asset::SLOT_BRANDING)
            ->where('key', $key)
            ->first()
            ?->url();
    }

    /**
     * Stores bytes as the `branding` asset under `$key`, replacing whatever was
     * there. Public by force: site branding renders on the login screen, so it
     * has to survive a logged-out request — callers do not get to decide that.
     *
     * Writing the logo re-derives its sizes; the derivative keys themselves are
     * written by the job through this same method, and dispatch is keyed on the
     * original so that cannot recurse.
     */
    public function store(string $key, string $contents, ?int $userId = null): Asset
    {
        $asset = app(AssetService::class)->storeContents(
            $contents,
            Asset::SLOT_BRANDING,
            $key,
            userId: $userId,
            isPublic: true,
        );

        if ($key === self::KEY_LOGO) {
            GenerateBrandingSizes::dispatch($asset->id);
        }

        return $asset;
    }

    /**
     * Removes the `branding` asset under `$key`, and with it the file — the row
     * and the bytes are one thing. A no-op when nothing is stored.
     */
    public function forget(string $key): void
    {
        app(AssetService::class)->find(Asset::SLOT_BRANDING, $key)?->delete();
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

        return $this->url(self::logoKey($size))
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
     * Favicon URL: an uploaded favicon, else the smallest logo derivative that
     * exists, else the bundled phpVMS icon.
     *
     * Walks {@see LOGO_SIZES} rather than reading `logo-32` alone so a
     * derivative run that produced only some sizes still yields a favicon.
     * Deliberately stops before the full-size original — a full-size logo is a
     * worse favicon than the bundled one.
     */
    public function favicon(): string
    {
        if ($uploaded = $this->url(self::KEY_FAVICON)) {
            return $uploaded;
        }

        foreach (self::LOGO_SIZES as $size) {
            if ($derivative = $this->url(self::logoKey($size))) {
                return $derivative;
            }
        }

        return asset('assets/img/favicon.png');
    }

    /**
     * Whether a favicon has been uploaded in its own right, as opposed to being
     * derived from the logo.
     */
    public function hasFavicon(): bool
    {
        return $this->url(self::KEY_FAVICON) !== null;
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
