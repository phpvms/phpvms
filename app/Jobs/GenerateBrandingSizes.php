<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Filament\Pages\Branding;
use App\Services\SettingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

/**
 * Generates 32/64/180px derivatives of an uploaded square logo and records
 * their URLs in `branding.logo_32_url`, `branding.logo_64_url` and
 * `branding.logo_180_url`. Dispatched from the logo autosave path in
 * {@see Branding::persistAutosavedField()}.
 *
 * Fails soft when neither GD nor Imagick is installed — `intervention/image`
 * only *suggests* those extensions and this app requires neither, so a throw
 * here would retry three times per upload and fill the failed_jobs table.
 * The derivative keys are cleared at the start of every run so a failed or
 * partial re-upload can never leave stale URLs pointing at the previous logo.
 */
class GenerateBrandingSizes implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var list<int> */
    private const array SIZES = [32, 64, 180];

    /**
     * @param string $diskPath path of the uploaded logo, relative to the
     *                         `filesystems.public_files` disk (e.g. `branding/logo.png`)
     */
    public function __construct(public string $diskPath) {}

    public function handle(): void
    {
        $settings = app(SettingService::class);

        foreach (self::SIZES as $size) {
            $settings->store("branding.logo_{$size}_url", '');
        }

        $driver = $this->availableDriver();

        if ($driver === null) {
            Log::warning('GenerateBrandingSizes: no image extension (GD or Imagick) available, skipping logo derivative generation.');

            return;
        }

        $manager = new ImageManager(['driver' => $driver]);

        $disk = Storage::disk(config('filesystems.public_files'));
        $extension = pathinfo($this->diskPath, PATHINFO_EXTENSION);
        $source = $disk->get($this->diskPath);

        foreach (self::SIZES as $size) {
            $path = "branding/logo-{$size}.{$extension}";

            $encoded = (string) $manager->make($source)->resize($size, $size)->encode();

            $disk->put($path, $encoded);

            $settings->store("branding.logo_{$size}_url", $disk->url($path));
        }
    }

    /**
     * The first available image extension, or null when neither GD nor
     * Imagick is installed. Extracted so tests can mock the condition
     * without runkit.
     */
    protected function availableDriver(): ?string
    {
        return match (true) {
            extension_loaded('gd')      => 'gd',
            extension_loaded('imagick') => 'imagick',
            default                     => null,
        };
    }
}
