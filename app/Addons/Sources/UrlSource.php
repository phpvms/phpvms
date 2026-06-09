<?php

declare(strict_types=1);

namespace App\Addons\Sources;

use App\Exceptions\AddonInstallException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Downloads a remote zip to a temp file, then delegates extraction to ZipSource.
 */
class UrlSource implements AddonSource
{
    public function __construct(
        private readonly string $url,
    ) {}

    public function fetch(string $stagingDir): string
    {
        File::ensureDirectoryExists($stagingDir);
        $tmpZip = $stagingDir.'/'.uniqid('dl_', true).'.zip';

        try {
            $response = Http::timeout(120)->get($this->url);
        } catch (Throwable $throwable) {
            throw new AddonInstallException(sprintf('Download failed: %s', $throwable->getMessage()), $throwable->getCode(), $throwable);
        }

        if (!$response->successful()) {
            throw new AddonInstallException(sprintf('Download failed (HTTP %d): %s', $response->status(), $this->url));
        }

        File::put($tmpZip, $response->body());

        $root = (new ZipSource($tmpZip))->fetch($stagingDir);

        File::delete($tmpZip);

        return $root;
    }
}
