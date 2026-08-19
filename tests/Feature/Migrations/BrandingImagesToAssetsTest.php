<?php

declare(strict_types=1);

use App\Features\Assets\AssetService;
use App\Models\Asset;
use App\Models\Setting;
use App\Support\Branding;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

function brandingImagesMigration(): object
{
    return require base_path('database/migrations_data/2026_08_17_000000_branding_images_to_assets.php');
}

function publicDisk()
{
    return Storage::disk(config('filesystems.public_files'));
}

/**
 * The seeder no longer creates these rows, so the tests have to. Written
 * through the query builder with an explicit id: `settings.id` is the formatted
 * key, not an autoincrement, and the model does not derive it on create.
 */
function seedBrandingUrl(string $key, string $value): void
{
    DB::table('settings')->updateOrInsert(
        ['id' => Setting::formatKey($key)],
        [
            'key'         => strtolower($key),
            'name'        => $key,
            'value'       => $value,
            'default'     => '',
            'group'       => 'branding',
            'type'        => 'hidden',
            'options'     => '',
            'description' => '',
            'created_at'  => now(),
            'updated_at'  => now(),
        ],
    );
}

beforeEach(function (): void {
    fakeAssetDisks();
});

it('moves a logo on the public disk into the branding slot and drops the setting', function (): void {
    publicDisk()->put('branding/logo.png', ASSET_TEST_PNG);
    $originalUrl = publicDisk()->url('branding/logo.png');
    seedBrandingUrl('branding.logo_url', $originalUrl);

    brandingImagesMigration()->up();

    $asset = app(AssetService::class)->find(Asset::SLOT_BRANDING, Branding::KEY_LOGO);

    expect($asset)->not->toBeNull()
        ->and($asset->content_type)->toBe('image/png')
        ->and($asset->is_public)->toBeTrue()
        ->and(Storage::disk($asset->diskName())->get($asset->path))->toBe(ASSET_TEST_PNG)
        ->and(Setting::find(Setting::formatKey('branding.logo_url')))->toBeNull();

    // The whole point of adopting rather than copying: the URL an install has
    // already published keeps working across the upgrade.
    expect($asset->path)->toBe('branding/logo.png')
        ->and($asset->url())->toBe($originalUrl);

    publicDisk()->assertExists('branding/logo.png');
});

/**
 * Adopting leaves exactly one copy of the bytes. Copying would double every
 * install's branding storage and orphan the original.
 */
it('does not duplicate the file it adopts', function (): void {
    publicDisk()->put('branding/logo.png', ASSET_TEST_PNG);
    seedBrandingUrl('branding.logo_url', publicDisk()->url('branding/logo.png'));

    brandingImagesMigration()->up();

    expect(publicDisk()->allFiles())->toBe(['branding/logo.png']);
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

    foreach ($keys as $settingKey => $assetKey) {
        // Distinct bytes per file, so a mis-mapped key cannot pass.
        publicDisk()->put("branding/{$assetKey}.png", ASSET_TEST_PNG."\x00".$assetKey);
        seedBrandingUrl($settingKey, publicDisk()->url("branding/{$assetKey}.png"));
    }

    brandingImagesMigration()->up();

    foreach ($keys as $settingKey => $assetKey) {
        $asset = app(AssetService::class)->find(Asset::SLOT_BRANDING, $assetKey);

        expect($asset)->not->toBeNull()
            ->and(Storage::disk($asset->diskName())->get($asset->path))->toBe(ASSET_TEST_PNG."\x00".$assetKey)
            ->and(Setting::find(Setting::formatKey($settingKey)))->toBeNull();
    }
});

/**
 * An admin who pasted a CDN URL has no file for us to copy. Dropping the
 * setting leaves that install on the bundled fallback until they re-upload,
 * which beats keeping a dead settings key nothing reads.
 */
it('drops a setting whose URL points somewhere we cannot read', function (): void {
    seedBrandingUrl('branding.logo_url', 'https://cdn.example.com/logo.png');

    brandingImagesMigration()->up();

    expect(app(AssetService::class)->find(Asset::SLOT_BRANDING, Branding::KEY_LOGO))->toBeNull()
        ->and(Setting::find(Setting::formatKey('branding.logo_url')))->toBeNull();
});

it('drops a setting whose file has since been deleted', function (): void {
    seedBrandingUrl('branding.logo_url', publicDisk()->url('branding/gone.png'));

    brandingImagesMigration()->up();

    expect(app(AssetService::class)->find(Asset::SLOT_BRANDING, Branding::KEY_LOGO))->toBeNull()
        ->and(Setting::find(Setting::formatKey('branding.logo_url')))->toBeNull();
});

it('leaves brand_color and site_name alone', function (): void {
    brandingImagesMigration()->up();

    expect(Setting::find(Setting::formatKey('branding.brand_color')))->not->toBeNull()
        ->and(Setting::find(Setting::formatKey('general.site_name')))->not->toBeNull();
});

it('is safe to run twice', function (): void {
    publicDisk()->put('branding/logo.png', ASSET_TEST_PNG);
    seedBrandingUrl('branding.logo_url', publicDisk()->url('branding/logo.png'));

    brandingImagesMigration()->up();
    brandingImagesMigration()->up();

    expect(Asset::query()->count())->toBe(1);
});

it('restores the rows empty on down', function (): void {
    brandingImagesMigration()->up();
    brandingImagesMigration()->down();

    foreach (['branding.logo_url', 'branding.logo_32_url', 'branding.logo_64_url', 'branding.logo_180_url', 'branding.logo_dark_url', 'branding.banner_url'] as $key) {
        expect(Setting::find(Setting::formatKey($key))?->value)->toBe('');
    }
});
