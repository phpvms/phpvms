<?php

declare(strict_types=1);

use App\Jobs\GenerateBrandingSizes;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

function derivativeSettingValue(int $size): ?string
{
    return Setting::where('id', Setting::formatKey("branding.logo_{$size}_url"))->value('value');
}

function putFakeLogo(string $path = 'branding/logo.png'): string
{
    Storage::disk(config('filesystems.public_files'))->putFileAs(
        'branding',
        UploadedFile::fake()->image('logo.png', 256, 256),
        basename($path),
    );

    return $path;
}

/**
 * A real, solid-colour PNG (UploadedFile::fake()->image() produces
 * visually-empty placeholders that resize to identical bytes regardless of
 * colour, so re-upload tests need genuinely different source pixels).
 */
function putSolidLogo(string $path, int $red): void
{
    $image = imagecreatetruecolor(256, 256);
    imagefill($image, 0, 0, imagecolorallocate($image, $red, 0, 0));
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    imagedestroy($image);

    Storage::disk(config('filesystems.public_files'))->put($path, $bytes);
}

it('writes three derivative files and three setting keys', function (): void {
    Storage::fake(config('filesystems.public_files'));
    $path = putFakeLogo();

    new GenerateBrandingSizes($path)->handle();

    $disk = Storage::disk(config('filesystems.public_files'));

    foreach ([32, 64, 180] as $size) {
        $disk->assertExists("branding/logo-{$size}.png");
        expect(derivativeSettingValue($size))->not->toBeEmpty();
    }
});

it('fails soft and leaves the derivative keys empty when no image extension is available', function (): void {
    Storage::fake(config('filesystems.public_files'));
    $path = putFakeLogo();

    Log::shouldReceive('warning')->once()->with(Mockery::pattern('/no image extension/i'));

    $job = Mockery::mock(GenerateBrandingSizes::class, [$path])->makePartial();
    $job->shouldAllowMockingProtectedMethods();
    $job->shouldReceive('availableDriver')->once()->andReturn(null);

    $job->handle();

    $disk = Storage::disk(config('filesystems.public_files'));

    foreach ([32, 64, 180] as $size) {
        $disk->assertMissing("branding/logo-{$size}.png");
        expect(derivativeSettingValue($size))->toBe('');
    }
});

it('regenerates the derivative files and keeps the setting keys filled on re-upload', function (): void {
    Storage::fake(config('filesystems.public_files'));
    $disk = Storage::disk(config('filesystems.public_files'));
    $path = 'branding/logo.png';
    putSolidLogo($path, red: 255);

    new GenerateBrandingSizes($path)->handle();
    $firstBytes = $disk->get('branding/logo-64.png');
    $firstUrl = derivativeSettingValue(64);

    // Re-upload: different image bytes land at the same deterministic path.
    putSolidLogo($path, red: 0);

    new GenerateBrandingSizes($path)->handle();

    foreach ([32, 64, 180] as $size) {
        $disk->assertExists("branding/logo-{$size}.png");
        expect(derivativeSettingValue($size))->not->toBeEmpty();
    }

    expect($disk->get('branding/logo-64.png'))->not->toBe($firstBytes)
        ->and(derivativeSettingValue(64))->toBe($firstUrl);
});

/**
 * The upload UI locks the crop editor to 1:1, but the editor is opt-in per
 * upload and nothing constrains an image set via a seeder or the API. So the
 * job must square a wide source itself. resize($s, $s) would stretch it;
 * fit($s, $s) crops to the best-fitting square first.
 */
it('produces square derivatives from a non-square source', function (): void {
    Storage::fake(config('filesystems.public_files'));

    $disk = Storage::disk(config('filesystems.public_files'));

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
    $bytes = ob_get_clean();
    imagedestroy($wide);

    $disk->put('branding/logo.png', $bytes);

    new GenerateBrandingSizes('branding/logo.png')->handle();

    foreach ([32, 64, 180] as $size) {
        $png = $disk->get("branding/logo-{$size}.png");
        $info = getimagesizefromstring($png);

        expect($info[0])->toBe($size)
            ->and($info[1])->toBe($size);

        $derivative = imagecreatefromstring($png);
        $topLeft = imagecolorsforindex($derivative, imagecolorat($derivative, 1, 1));
        imagedestroy($derivative);

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
it('points every size at the original for an SVG logo instead of failing', function (): void {
    Storage::fake(config('filesystems.public_files'));

    $disk = Storage::disk(config('filesystems.public_files'));
    $disk->put('branding/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>');

    new GenerateBrandingSizes('branding/logo.svg')->handle();

    $expected = $disk->url('branding/logo.svg');

    foreach ([32, 64, 180] as $size) {
        expect(derivativeSettingValue($size))->toBe($expected);
    }

    // No raster derivatives were written.
    expect($disk->exists('branding/logo-32.svg'))->toBeFalse();
});
