<?php

declare(strict_types=1);

namespace App\Support;

use App\Features\Assets\AssetService;
use App\Http\Data\BrandingData;
use App\Jobs\GenerateBrandingSizes;
use App\Models\Asset;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Cache;

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
 * Callers hand over bytes, not decisions: which slot, which disk, and whether a
 * write needs derivatives generated are all settled here.
 * The one piece deliberately outside is the image resizing itself, which is
 * queued work and so has to be a job — {@see GenerateBrandingSizes}, dispatched
 * by `store()` and writing back through it.
 *
 * Reads go through {@see getBranding()}: one cached {@see BrandingData} holding
 * every value, assembled with a single `assets` query. It is cached in the
 * shared cache store (`cache.keys.BRANDING`) and held for the rest of the request
 * as a scoped container instance, not on this class — the class is a singleton
 * that Octane keeps across requests, while scoped instances are reset per
 * request. Anything that changes what it holds must call {@see flush()}: the
 * `Asset` model's saved/deleted hooks do for the `branding` slot, and
 * `SettingsChangeSubscriber` does for {@see SETTING_KEYS}.
 */
final class Branding
{
    /**
     * Keys of the settings {@see getBranding()} reads. A change to any of them
     * must {@see flush()}.
     *
     * @var list<string>
     */
    public const array SETTING_KEYS = ['general.site_name', 'branding.brand_color'];

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
     * The cached branding values.
     *
     * Two tiers: the shared cache, so a page does not rebuild it, and a scoped
     * container instance, so the dozen accessor calls in one page do not each
     * make a cache round trip. The scoped binding is registered here, on first
     * use, so this class owns its own memo.
     *
     * The shared cache holds the array form, not the DTO: `cache.serializable_classes`
     * is false, so a persistent store refuses to unserialize objects and would
     * hand back an incomplete class on the next request.
     */
    public function getBranding(): BrandingData
    {
        if (!app()->bound(BrandingData::class)) {
            app()->scoped(BrandingData::class, function (): BrandingData {
                $cache = config('cache.keys.BRANDING');

                return BrandingData::from(
                    Cache::remember($cache['key'], $cache['time'], fn (): array => $this->assemble()->toArray())
                );
            });
        }

        return app(BrandingData::class);
    }

    /**
     * Drop the cached branding, in the shared cache and for this request, so
     * the next read reassembles it.
     */
    public static function flush(): void
    {
        Cache::forget(config('cache.keys.BRANDING.key'));
        app()->forgetInstance(BrandingData::class);
    }

    /**
     * One `assets` query for the whole `branding` slot, plus the two settings.
     */
    private function assemble(): BrandingData
    {
        $urls = Asset::query()
            ->slot(Asset::SLOT_BRANDING)
            ->get()
            ->mapWithKeys(fn (Asset $asset): array => [$asset->key => $asset->url()])
            ->filter(fn (?string $url): bool => $url !== null);

        $logoSizes = [];
        foreach (self::LOGO_SIZES as $size) {
            if ($url = $urls->get(self::logoKey($size))) {
                $logoSizes[$size] = $url;
            }
        }

        return new BrandingData(
            siteName: (string) setting('general.site_name', ''),
            brandColor: (string) setting('branding.brand_color', ''),
            logo: $urls->get(self::KEY_LOGO),
            logoDark: $urls->get(self::KEY_LOGO_DARK),
            favicon: $urls->get(self::KEY_FAVICON),
            banner: $urls->get(self::KEY_BANNER),
            logoSizes: $logoSizes,
            hasLogo: $urls->has(self::KEY_LOGO),
            hasDarkLogo: $urls->has(self::KEY_LOGO_DARK),
            hasFavicon: $urls->has(self::KEY_FAVICON),
        );
    }

    /**
     * Stores bytes as the `branding` asset under `$key`, replacing whatever was
     * there. Always on the public disk: site branding renders on the login
     * screen, so it has to be fetchable without a session — callers do not get
     * to decide that.
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
            storage: (string) config('filesystems.public_files'),
        );

        self::flush();

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

        self::flush();
    }

    /**
     * Airline display name. Falls back to `config('app.name')` when
     * `general.site_name` is empty.
     */
    public function name(): string
    {
        return $this->getBranding()->siteName ?: (string) config('app.name');
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

        $branding = $this->getBranding();

        if ($size === null) {
            return $branding->logo ?? $default;
        }

        return $branding->logoSizes[$size] ?? $branding->logo ?? $default;
    }

    /**
     * Whether an airline logo has been uploaded. Lets a call site keep its
     * own pre-existing default asset instead of `logo()`'s bundled fallback,
     * for places where that fallback is a different asset than what used to
     * render there.
     */
    public function hasLogo(): bool
    {
        return $this->getBranding()->hasLogo;
    }

    /**
     * Dark-mode logo URL. Falls back to the light logo when no dark logo has
     * been uploaded, so an install with nothing configured is unchanged.
     */
    public function logoDark(): string
    {
        return $this->getBranding()->logoDark ?? $this->logo();
    }

    /**
     * Whether a dark-mode logo has been uploaded.
     */
    public function hasDarkLogo(): bool
    {
        return $this->getBranding()->hasDarkLogo;
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
        $branding = $this->getBranding();

        if ($branding->favicon !== null) {
            return $branding->favicon;
        }

        foreach (self::LOGO_SIZES as $size) {
            if (isset($branding->logoSizes[$size])) {
                return $branding->logoSizes[$size];
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
        return $this->getBranding()->hasFavicon;
    }

    /**
     * Banner URL, or null when none has been uploaded.
     */
    public function banner(): ?string
    {
        return $this->getBranding()->banner;
    }

    /**
     * Brand colour as a hex string. Falls back to the phpVMS blue.
     */
    public function brandColor(): string
    {
        return $this->getBranding()->brandColor ?: self::DEFAULT_BRAND_COLOR;
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
