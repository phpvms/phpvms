<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Features\Assets\AssetService;
use App\Features\Assets\Enums\AssetSlot;
use App\Features\Assets\Models\Asset;
use App\Filament\Pages\Branding;
use App\Services\ImageUploadService;
use App\Support\Branding as BrandingSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Generates 32/64/180px derivatives of an uploaded square logo and stores them
 * as `branding` assets under `logo-32`, `logo-64` and `logo-180`. Dispatched
 * from the logo autosave path in {@see Branding::persistAutosavedField()}.
 *
 * Fails soft when neither GD nor Imagick is installed — `intervention/image`
 * only *suggests* those extensions and this app requires neither, so a throw
 * here would retry three times per upload and fill the failed_jobs table.
 * The derivative assets are deleted at the start of every run so a failed or
 * partial re-upload can never leave a stale derivative of the previous logo —
 * `Branding::favicon()` falls back to the bundled icon, which is correct, where
 * a stale row would show the old brand.
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
     * @param string $assetId id of the stored `branding`/`logo` asset
     */
    public function __construct(public string $assetId) {}

    public function handle(): void
    {
        $assets = app(AssetService::class);

        foreach (self::SIZES as $size) {
            $assets->find(AssetSlot::BRANDING, $this->key($size))?->delete();
        }

        $source = Asset::query()->find($this->assetId);

        // The logo was replaced or deleted between the upload and this run.
        // Nothing to derive from, and the cleared derivatives above are already
        // the right end state — but say so, because a silent return here looks
        // identical to a job that never ran.
        if ($source === null) {
            Log::warning("GenerateBrandingSizes: source asset [{$this->assetId}] no longer exists, skipping.");

            return;
        }

        $extension = $source->type->extensionFor($source->content_type) ?? '';

        // An SVG needs no derivatives -- it is resolution-independent, so the
        // same file serves as favicon, switcher icon and full-size logo. It
        // also CANNOT be rasterised here: GD decodes binary image formats and
        // throws NotReadableException("Unable to init from given binary data")
        // on `<svg ...`, which is what every SVG logo upload used to fail with.
        // Copy the original into each size slot instead of erroring.
        if ($extension === 'svg') {
            $contents = (string) Storage::disk($source->diskName())->get($source->path);

            foreach (self::SIZES as $size) {
                $assets->storeContents($contents, AssetSlot::BRANDING, $this->key($size), isPublic: true);
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

        // The whole derivative path is soft, not just the driver check above:
        // a source deleted from the disk between upload and run, or a file the
        // chosen driver cannot decode, would otherwise retry three times into
        // failed_jobs over what is a cosmetic favicon. The size keys are
        // already cleared, so bailing here leaves exactly the empty state the
        // class contract describes.
        try {
            $this->writeDerivatives($manager, $source, $format, $assets);
        } catch (Throwable $throwable) {
            Log::warning('GenerateBrandingSizes: could not generate logo derivatives: '.$throwable->getMessage());
        }
    }

    /** Asset key for a derivative — `logo-32`, `logo-64`, `logo-180`. */
    private function key(int $size): string
    {
        return BrandingSupport::KEY_LOGO.'-'.$size;
    }

    private function writeDerivatives(ImageManager $manager, Asset $sourceAsset, string $format, AssetService $assets): void
    {
        $source = (string) Storage::disk($sourceAsset->diskName())->get($sourceAsset->path);

        foreach (self::SIZES as $size) {
            // fit(), not resize(): resize($size, $size) forces both dimensions
            // and STRETCHES a non-square source. The upload UI offers a 1:1
            // crop but the admin can decline it, and nothing constrains an
            // image set through a seeder or the API, so the job cannot assume a
            // square input. fit() picks the best-fitting square, crops to it,
            // then scales -- correct for any source aspect.
            $encoded = (string) $manager->make($source)->fit($size, $size)->encode($format, ImageUploadService::WEBP_QUALITY);

            // Public like the logo they derive from: the 32px one is the
            // favicon, which the login screen requests before anyone is
            // logged in.
            $assets->storeContents($encoded, AssetSlot::BRANDING, $this->key($size), isPublic: true);
        }
    }
}
