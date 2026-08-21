<?php

declare(strict_types=1);

use App\Features\Assets\AssetService;
use App\Models\Asset;
use App\Models\Setting;
use App\Support\Branding;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

function brandingImagesMigration(): object
{
    return require base_path('database/migrations_data/2026_08_17_000000_branding_images_to_assets.php');
}

/**
 * The seeder no longer creates these rows, so the tests have to. Written
 * through the query builder with an explicit id: `settings.id` is the formatted
 * key, not an autoincrement, and the model does not derive it on create.
 */
function seedBrandingUrl(string $key, string $value, string $type = 'hidden'): void
{
    DB::table('settings')->updateOrInsert(
        ['id' => Setting::formatKey($key)],
        [
            'key'         => strtolower($key),
            'name'        => $key,
            'value'       => $value,
            'default'     => '',
            'group'       => 'branding',
            'type'        => $type,
            'options'     => '',
            'description' => '',
            'created_at'  => now(),
            'updated_at'  => now(),
        ],
    );
}

/**
 * up() only flips `type`: the row stays, with the URL it held. Nothing reads it
 * any more, but the value is the only record of where an image came from.
 */
function expectSettingKeptHidden(string $key, string $value): void
{
    $setting = Setting::find(Setting::formatKey($key));

    expect($setting)->not->toBeNull()
        ->and($setting->type)->toBe('hidden')
        ->and($setting->value)->toBe($value);
}

/**
 * The one query `App\Filament\Pages\Settings` builds both its form state and
 * its tabs from (`app/Filament/Pages/Settings.php:96,175`). Asserted directly
 * rather than through the Livewire page, matching
 * `tests/Feature/BrandingSettingsPageTest.php:15-18`.
 */
function settingsPageKeys(): Collection
{
    return Setting::where('type', '!=', 'hidden')->orderBy('order')->get()->pluck('key');
}

it('stores the setting URL as a link asset in the branding slot', function (): void {
    $url = 'https://example.com/storage/branding/logo.png';
    seedBrandingUrl('branding.logo_url', $url);

    brandingImagesMigration()->up();

    $asset = app(AssetService::class)->find(Asset::SLOT_BRANDING, Branding::KEY_LOGO);

    // The URL is what moves. Nothing is read, copied or checked for existence,
    // so the string an install has already published comes back unchanged.
    expect($asset)->not->toBeNull()
        ->and($asset->storage)->toBe(Asset::STORAGE_URL)
        ->and($asset->path)->toBe($url)
        ->and($asset->url())->toBe($url);

    expectSettingKeptHidden('branding.logo_url', $url);
});

/**
 * A URL on someone else's host is the same thing as one on ours: a URL that
 * renders. The older shape of this migration dropped these, which cost an
 * install its branding for a CDN link or a URL written before a domain move.
 */
it('stores an external URL as a link asset too', function (): void {
    seedBrandingUrl('branding.logo_url', 'https://cdn.example.com/logo.png');

    brandingImagesMigration()->up();

    $asset = app(AssetService::class)->find(Asset::SLOT_BRANDING, Branding::KEY_LOGO);

    expect($asset)->not->toBeNull()
        ->and($asset->url())->toBe('https://cdn.example.com/logo.png');
});

it('maps every image key to its asset key', function (): void {
    $keys = [
        'branding.logo_url'      => Branding::KEY_LOGO,
        'branding.logo_32_url'   => Branding::KEY_LOGO.'-32',
        'branding.logo_64_url'   => Branding::KEY_LOGO.'-64',
        'branding.logo_180_url'  => Branding::KEY_LOGO.'-180',
        'branding.logo_dark_url' => Branding::KEY_LOGO_DARK,
        'branding.banner_url'    => Branding::KEY_BANNER,
    ];

    // A distinct URL per key, so a mis-mapped key cannot pass.
    foreach ($keys as $settingKey => $assetKey) {
        seedBrandingUrl($settingKey, "https://example.com/branding/{$assetKey}.png");
    }

    brandingImagesMigration()->up();

    foreach ($keys as $settingKey => $assetKey) {
        $asset = app(AssetService::class)->find(Asset::SLOT_BRANDING, $assetKey);

        expect($asset)->not->toBeNull()
            ->and($asset->path)->toBe("https://example.com/branding/{$assetKey}.png");

        expectSettingKeptHidden($settingKey, "https://example.com/branding/{$assetKey}.png");
    }
});

/**
 * storeLink() refuses anything that is not an absolute http(s) URL, and one bad
 * value must cost that install a re-upload rather than the upgrade: the loop
 * logs it and carries on to the keys behind it.
 */
it('skips a value that is not an absolute http url without aborting the run', function (): void {
    Log::spy();

    seedBrandingUrl('branding.logo_url', '/storage/branding/logo.png');
    seedBrandingUrl('branding.banner_url', 'https://example.com/branding/banner.png');

    brandingImagesMigration()->up();

    $assets = app(AssetService::class);

    expect($assets->find(Asset::SLOT_BRANDING, Branding::KEY_LOGO))->toBeNull()
        ->and($assets->find(Asset::SLOT_BRANDING, Branding::KEY_BANNER))->not->toBeNull();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => str_contains($message, 'could not move branding image'))
        ->once();

    // Both rows are still hidden with their values: the refused one is the only
    // record of the URL an admin has to re-upload.
    expectSettingKeptHidden('branding.logo_url', '/storage/branding/logo.png');
    expectSettingKeptHidden('branding.banner_url', 'https://example.com/branding/banner.png');
});

/**
 * The row is kept because it is inert, and it is inert because `hidden` is
 * what keeps it off the Settings page. Seeded visible here so the assertion
 * fails if up() stops flipping `type`.
 */
it('hides the row from the settings page', function (): void {
    seedBrandingUrl('branding.logo_url', 'https://cdn.example.com/logo.png', type: 'text');

    expect(settingsPageKeys())->toContain('branding.logo_url');

    brandingImagesMigration()->up();

    expect(settingsPageKeys())->not->toContain('branding.logo_url');

    expectSettingKeptHidden('branding.logo_url', 'https://cdn.example.com/logo.png');
});

it('leaves brand_color and site_name alone', function (): void {
    brandingImagesMigration()->up();

    expect(Setting::find(Setting::formatKey('branding.brand_color')))->not->toBeNull()
        ->and(Setting::find(Setting::formatKey('general.site_name')))->not->toBeNull();
});

it('is safe to run twice', function (): void {
    seedBrandingUrl('branding.logo_url', 'https://example.com/branding/logo.png');

    brandingImagesMigration()->up();
    brandingImagesMigration()->up();

    expect(Asset::query()->count())->toBe(1);
    expectSettingKeptHidden('branding.logo_url', 'https://example.com/branding/logo.png');
});

/**
 * There is nothing to undo for a run of the current up(), which only flips
 * `type` on rows it leaves in place — so down() must not touch the value it
 * finds.
 */
it('leaves a surviving row untouched on down', function (): void {
    $url = 'https://example.com/branding/logo.png';
    seedBrandingUrl('branding.logo_url', $url);

    brandingImagesMigration()->up();
    brandingImagesMigration()->down();

    expectSettingKeptHidden('branding.logo_url', $url);
});

/**
 * The insert path only covers the older shape of this migration, which deleted
 * the rows: the keys come back, empty, because nothing records which row held
 * which URL.
 */
it('restores deleted rows empty on down', function (): void {
    $keys = ['branding.logo_url', 'branding.logo_32_url', 'branding.logo_64_url', 'branding.logo_180_url', 'branding.logo_dark_url', 'branding.banner_url'];

    $ids = array_map(Setting::formatKey(...), $keys);
    DB::table('settings')->whereIn('id', $ids)->delete();

    brandingImagesMigration()->down();

    foreach ($keys as $key) {
        expectSettingKeptHidden($key, '');
    }
});
