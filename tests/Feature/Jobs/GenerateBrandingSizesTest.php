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
