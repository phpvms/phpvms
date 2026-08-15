<?php

declare(strict_types=1);

namespace App\Services;

use enshrined\svgSanitize\Sanitizer;
use Filament\Forms\Components\BaseFileUpload;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;
use Intervention\Image\Exception\NotReadableException;
use Intervention\Image\ImageManager;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

/**
 * Single code path for every admin-panel image upload: converts a raster
 * upload to WebP at store time instead of keeping whatever format the admin
 * dropped in. Wired into Branding, AirlineForm and AwardForm via
 * `saveUploadedFileUsing(fn ($component, $file) => ...->storeFilamentUpload(...))`.
 *
 * SVGs are sanitized and stored as-is rather than rasterised -- GD throws
 * NotReadableException on `<svg`, see {@see GenerateBrandingSizes}, and the
 * point of a vector logo is that it stays vector. Anything else GD/Imagick
 * cannot decode is stored byte-for-byte.
 * An install with no WebP-capable driver keeps working unconverted: this
 * must degrade, never throw. The one exception is an SVG that will not parse
 * as XML, which is rejected rather than served -- see {@see sanitizeSvg()}.
 *
 * Stateless, so it is Octane-safe as a singleton with no entry needed in
 * config/octane.php's `flush` array.
 */
class ImageUploadService
{
    /** Passed to Intervention's encode(); not exposed as a setting. */
    public const int WEBP_QUALITY = 82;

    /**
     * Store an uploaded file, converting it to WebP when a driver supports
     * it. Returns the stored path relative to $disk.
     *
     * @param ?string $basename filename without extension; a ULID is generated when null
     */
    public function store(TemporaryUploadedFile $file, string $disk, string $directory, ?string $basename = null): string
    {
        $storage = Storage::disk($disk);
        $basename ??= (string) Str::ulid();
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'svg') {
            return $this->storeVerbatim($storage, $file, $directory, $basename, $extension, $this->sanitizeSvg($file->get()));
        }

        $driver = $this->webpDriver();

        if ($driver === null) {
            Log::warning('ImageUploadService: no WebP-capable image driver (GD or Imagick) available, storing original.');

            return $this->storeVerbatim($storage, $file, $directory, $basename, $extension);
        }

        try {
            $encoded = (string) new ImageManager(['driver' => $driver])
                ->make($file->getRealPath())
                ->encode('webp', self::WEBP_QUALITY);
        } catch (NotReadableException) {
            return $this->storeVerbatim($storage, $file, $directory, $basename, $extension);
        }

        $path = trim($directory.'/'.$basename.'.webp', '/');
        $storage->put($path, $encoded);
        rescue(fn () => $storage->setVisibility($path, 'public'), report: false);

        return $path;
    }

    /**
     * Wire a Filament FileUpload's saveUploadedFileUsing() to store(),
     * reusing whatever getUploadedFileNameForStorageUsing() the field
     * already has configured (or Filament's ULID default) for the basename
     * -- so a field's naming rule stays defined in exactly one place.
     */
    public function storeFilamentUpload(BaseFileUpload $component, TemporaryUploadedFile $file): string
    {
        $basename = pathinfo($component->getUploadedFileNameForStorage($file), PATHINFO_FILENAME);

        return $this->store($file, $component->getDiskName(), (string) $component->getDirectory(), $basename);
    }

    /**
     * The first available WebP-capable image driver, or null when neither
     * GD nor Imagick can encode WebP. Public so {@see GenerateBrandingSizes}
     * shares this check instead of duplicating it.
     */
    public function webpDriver(): ?string
    {
        return match (true) {
            extension_loaded('gd') && (gd_info()['WebP Support'] ?? false)      => 'gd',
            extension_loaded('imagick') && Imagick::queryFormats('WEBP') !== [] => 'imagick',
            default                                                             => null,
        };
    }

    /**
     * The first available image driver regardless of WebP support.
     *
     * A GD build compiled without WebP can still decode and re-encode PNG or
     * JPEG. Callers that resize rather than convert -- {@see GenerateBrandingSizes}
     * -- must fall back to this, otherwise such an install loses its
     * derivatives entirely and `Branding::favicon()` silently drops back to the
     * bundled phpVMS icon.
     */
    public function rasterDriver(): ?string
    {
        return match (true) {
            extension_loaded('gd')      => 'gd',
            extension_loaded('imagick') => 'imagick',
            default                     => null,
        };
    }

    /**
     * Strip anything executable out of an SVG before it reaches public storage.
     *
     * An SVG is an XML document, not an opaque image: served from our own
     * origin, a `<script>` or an `onload=` inside one runs with the site's
     * cookies as soon as anyone opens the file's URL directly. `<img src>`
     * never executes it, which is why the logo rendering everywhere looks
     * harmless. Uploads are admin-only, so this guards admin-tier escalation
     * and a link handed to someone else, not an anonymous attack.
     *
     * @throws RuntimeException when the upload is not parseable as XML at all,
     *                          which is not something to store and serve
     */
    private function sanitizeSvg(string $contents): string
    {
        $clean = new Sanitizer()->sanitize($contents);

        if ($clean === false) {
            throw new RuntimeException('The uploaded SVG could not be parsed and was not stored.');
        }

        return $clean;
    }

    private function storeVerbatim(Filesystem $storage, TemporaryUploadedFile $file, string $directory, string $basename, string $extension, ?string $contents = null): string
    {
        $path = trim($directory.'/'.$basename.'.'.$extension, '/');
        $storage->put($path, $contents ?? $file->get());
        rescue(fn () => $storage->setVisibility($path, 'public'), report: false);

        return $path;
    }
}
