<?php

declare(strict_types=1);

use App\Features\Assets\AssetService;
use App\Jobs\GenerateBrandingSizes;
use App\Models\Asset;
use App\Services\ImageUploadService;
use App\Support\Branding;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

function derivative(int $size): ?Asset
{
    return app(AssetService::class)->find(Asset::SLOT_BRANDING, Branding::KEY_LOGO.'-'.$size);
}

/**
 * A real, solid-colour PNG stored as the `logo` asset. A placeholder image will
 * not do: {@see UploadedFile::fake()} produces visually-empty images that
 * resize to identical bytes regardless of colour, so a re-upload test could not
 * tell a regenerated derivative from a stale one.
 */
function putLogoAsset(int $red = 255): Asset
{
    $image = imagecreatetruecolor(256, 256);
    imagefill($image, 0, 0, imagecolorallocate($image, $red, 0, 0));
    ob_start();
    imagepng($image);
    $bytes = (string) ob_get_clean();
    imagedestroy($image);

    return app(AssetService::class)->storeContents($bytes, Asset::SLOT_BRANDING, Branding::KEY_LOGO, storage: (string) config('filesystems.public_files'));
}

beforeEach(function (): void {
    fakeAssetDisks();
});

/** Branding assets are public, so their bytes live on the public disk. */
function assetDisk(Asset $asset): Filesystem
{
    return Storage::disk($asset->diskName());
}

it('writes three derivative assets', function (): void {
    $logo = putLogoAsset();

    new GenerateBrandingSizes($logo->id)->handle();

    foreach ([32, 64, 180] as $size) {
        $asset = derivative($size);

        expect($asset)->not->toBeNull()
            ->and($asset->content_type)->toBe('image/webp')
            // Public, because the 32px one is the favicon the login screen asks
            // for before anyone has logged in.
            ->and($asset->storage)->toBe(config('filesystems.public_files'));

        assetDisk($asset)->assertExists($asset->path);
    }
});

it('fails soft and writes no derivatives when no image extension is available', function (): void {
    $logo = putLogoAsset();

    Log::shouldReceive('warning')->once()->with(Mockery::pattern('/no image extension/i'));

    // "No image extension" is a property of the environment, so it is expressed
    // on the service both the job and the upload path consult.
    $service = Mockery::mock(ImageUploadService::class)->makePartial();
    $service->shouldReceive('webpDriver')->andReturn(null);
    $service->shouldReceive('rasterDriver')->andReturn(null);
    app()->instance(ImageUploadService::class, $service);

    new GenerateBrandingSizes($logo->id)->handle();

    foreach ([32, 64, 180] as $size) {
        expect(derivative($size))->toBeNull();
    }
});

it('regenerates the derivatives on re-upload', function (): void {
    $logo = putLogoAsset(red: 255);

    new GenerateBrandingSizes($logo->id)->handle();
    $firstBytes = assetDisk(derivative(64))->get(derivative(64)->path);

    // Re-upload: different pixels, same (slot, key), so the same asset row.
    $logo = putLogoAsset(red: 0);

    new GenerateBrandingSizes($logo->id)->handle();

    foreach ([32, 64, 180] as $size) {
        expect(derivative($size))->not->toBeNull();
    }

    expect(assetDisk(derivative(64))->get(derivative(64)->path))->not->toBe($firstBytes);
});

/**
 * The upload UI locks the crop editor to 1:1, but the editor is opt-in per
 * upload and nothing constrains an image set via a seeder or the API. So the
 * job must square a wide source itself. resize($s, $s) would stretch it;
 * fit($s, $s) crops to the best-fitting square first.
 */
it('produces square derivatives from a non-square source', function (): void {
    // 400x100, blue everywhere except a green band in the leftmost 50px.
    // fit() takes the best-fitting square from the CENTRE (x 150-250), so the
    // green never survives. resize() would squash the full width into the
    // square and keep it. Asserting dimensions alone would not tell the two
    // apart -- resize($s, $s) also yields exactly $s x $s -- so this samples a
    // pixel instead.
    $wide = imagecreatetruecolor(400, 100);
    imagefill($wide, 0, 0, imagecolorallocate($wide, 10, 120, 200));
    imagefilledrectangle($wide, 0, 0, 49, 99, imagecolorallocate($wide, 0, 200, 0));
    ob_start();
    imagepng($wide);
    $bytes = (string) ob_get_clean();
    imagedestroy($wide);

    $logo = app(AssetService::class)->storeContents($bytes, Asset::SLOT_BRANDING, Branding::KEY_LOGO, storage: (string) config('filesystems.public_files'));

    new GenerateBrandingSizes($logo->id)->handle();

    foreach ([32, 64, 180] as $size) {
        $webp = (string) assetDisk(derivative($size))->get(derivative($size)->path);
        $info = getimagesizefromstring($webp);

        expect($info[0])->toBe($size)
            ->and($info[1])->toBe($size);

        $image = imagecreatefromstring($webp);
        $topLeft = imagecolorsforindex($image, imagecolorat($image, 1, 1));
        imagedestroy($image);

        // Blue-dominant means the centre crop won; green-dominant means the
        // source was stretched instead of cropped.
        expect($topLeft['blue'])->toBeGreaterThan($topLeft['green']);
    }
});

/**
 * An SVG cannot be rasterised by GD -- intervention throws
 * NotReadableException("Unable to init from given binary data") on `<svg ...`.
 * It also does not need to be: one resolution-independent file serves every
 * size. Every SVG logo upload used to land in failed_jobs because of this.
 */
it('copies the original into every size for an SVG logo instead of failing', function (): void {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>';
    $logo = app(AssetService::class)->storeContents($svg, Asset::SLOT_BRANDING, Branding::KEY_LOGO, storage: (string) config('filesystems.public_files'));

    new GenerateBrandingSizes($logo->id)->handle();

    foreach ([32, 64, 180] as $size) {
        $asset = derivative($size);

        expect($asset)->not->toBeNull()
            ->and($asset->content_type)->toBe('image/svg+xml')
            ->and(assetDisk($asset)->get($asset->path))->toBe($svg);
    }
});

/**
 * A GD build compiled without WebP can still decode and resize PNG. Routing the
 * job's driver check through webpDriver() made it skip derivatives entirely on
 * such an install, costing it the favicon -- resizing does not need WebP, only
 * the encode format does.
 */
it('still writes derivatives in the source format when webp is unavailable', function (): void {
    $logo = putLogoAsset(40);

    $service = Mockery::mock(ImageUploadService::class)->makePartial();
    $service->shouldReceive('webpDriver')->andReturn(null);
    $service->shouldReceive('rasterDriver')->andReturn('gd');
    app()->instance(ImageUploadService::class, $service);

    new GenerateBrandingSizes($logo->id)->handle();

    foreach ([32, 64, 180] as $size) {
        expect(derivative($size)?->content_type)->toBe('image/png');
    }
});

/**
 * pullfrog caught this: choosing the format from webpDriver() while encoding
 * with rasterDriver() breaks on a GD-without-WebP + Imagick-with-WebP build.
 * rasterDriver() prefers GD whenever it is loaded, so GD would be handed a
 * .webp source it cannot decode and the job would throw instead of failing
 * soft. Format and encoder must come from the same driver.
 */
it('encodes with the same driver that chose the format', function (): void {
    $logo = putLogoAsset(90);

    // GD loaded but WebP-incapable; Imagick can do WebP.
    $service = Mockery::mock(ImageUploadService::class)->makePartial();
    $service->shouldReceive('webpDriver')->andReturn('imagick');
    $service->shouldReceive('rasterDriver')->andReturn('gd');
    app()->instance(ImageUploadService::class, $service);

    new GenerateBrandingSizes($logo->id)->handle();

    // webp was chosen, so the imagick driver must have done the encoding.
    foreach ([32, 64, 180] as $size) {
        expect(derivative($size)?->content_type)->toBe('image/webp');
    }
})->skip(
    !extension_loaded('imagick'),
    // The mock names imagick as the WebP driver, so the job really does
    // construct an Imagick ImageManager and intervention throws
    // NotSupportedException without the extension. Nothing about the job under
    // test can make this pass on a build that has no imagick.
    'requires the imagick extension',
);

/**
 * The fail-soft contract covers the whole derivative path, not just a missing
 * GD/Imagick. A source deleted between the upload and the queue run would
 * otherwise throw out of handle() and retry into failed_jobs over what is a
 * favicon.
 */
it('fails soft and writes no derivatives when the source asset is gone', function (): void {
    Log::shouldReceive('warning')->once()->with(Mockery::pattern('/no longer exists/i'));

    new GenerateBrandingSizes('never-stored-id')->handle();

    foreach ([32, 64, 180] as $size) {
        expect(derivative($size))->toBeNull();
    }
});

/**
 * The row can survive while its file does not. That path has to warn and stop,
 * not throw.
 */
it('fails soft when the source row survives but its file is gone', function (): void {
    $logo = putLogoAsset();
    assetDisk($logo)->delete($logo->path);

    Log::shouldReceive('warning')->once()->with(Mockery::pattern('/could not generate logo derivatives/i'));

    new GenerateBrandingSizes($logo->id)->handle();

    foreach ([32, 64, 180] as $size) {
        expect(derivative($size))->toBeNull();
    }
});
