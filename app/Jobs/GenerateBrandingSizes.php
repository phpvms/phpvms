<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Filament\Pages\Branding;
use App\Services\ImageUploadService;
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

        $disk = Storage::disk(config('filesystems.public_files'));
        $extension = strtolower(pathinfo($this->diskPath, PATHINFO_EXTENSION));

        // An SVG needs no derivatives -- it is resolution-independent, so the
        // same file serves as favicon, switcher icon and full-size logo. It
        // also CANNOT be rasterised here: GD decodes binary image formats and
        // throws NotReadableException("Unable to init from given binary data")
        // on `<svg ...`, which is what every SVG logo upload used to fail with.
        // Point the size keys at the original instead of erroring.
        if ($extension === 'svg') {
            $url = $disk->url($this->diskPath);

            foreach (self::SIZES as $size) {
                $settings->store("branding.logo_{$size}_url", $url);
            }

            return;
        }

        // One driver decides BOTH the format and the encoding, and they must be
        // the same driver. Picking the format from webpDriver() while encoding
        // with rasterDriver() breaks on a GD-without-WebP + Imagick-with-WebP
        // build: rasterDriver() prefers GD whenever it is loaded, so GD would be
        // handed a .webp source it cannot decode, throw NotReadableException,
        // and retry into failed_jobs -- the opposite of this job's fail-soft
        // contract, and it would cost that install its favicon.
        //
        // Prefer the WebP-capable driver. Fall back to any raster driver and the
        // source format, because a GD build without WebP still resizes PNG and
        // JPEG perfectly well and skipping derivatives there would be worse.
        $service = app(ImageUploadService::class);
        $driver = $service->webpDriver();
        $format = 'webp';

        if ($driver === null) {
            $driver = $service->rasterDriver();
            $format = $extension;
        }

        if ($driver === null) {
            Log::warning('GenerateBrandingSizes: no image extension (GD or Imagick) available, skipping logo derivative generation.');

            return;
        }

        $manager = new ImageManager(['driver' => $driver]);

        $source = $disk->get($this->diskPath);

        foreach (self::SIZES as $size) {
            $path = "branding/logo-{$size}.{$format}";

            // fit(), not resize(): resize($size, $size) forces both dimensions
            // and STRETCHES a non-square source. The upload UI offers a 1:1
            // crop but the admin can decline it, and nothing constrains an
            // image set through a seeder or the API, so the job cannot assume a
            // square input. fit() picks the best-fitting square, crops to it,
            // then scales -- correct for any source aspect.
            $encoded = (string) $manager->make($source)->fit($size, $size)->encode($format, ImageUploadService::WEBP_QUALITY);

            $disk->put($path, $encoded);

            // Explicit, matching ImageUploadService::store(). `public_files` can
            // point at S3/R2 (config/filesystems.php), where a bare put() takes
            // the disk default and the derivative URLs would 403 while the
            // original logo renders. rescue() because a local disk has no
            // per-object visibility to set.
            rescue(fn () => $disk->setVisibility($path, 'public'), report: false);

            $settings->store("branding.logo_{$size}_url", $disk->url($path));
        }
    }
}
