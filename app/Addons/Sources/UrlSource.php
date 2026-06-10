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
            // sink() streams the response straight to disk — never buffers the
            // whole (potentially huge) body in memory.
            $response = Http::timeout(120)->sink($tmpZip)->get($this->url);
        } catch (Throwable $throwable) {
            File::delete($tmpZip);

            throw new AddonInstallException(sprintf('Download failed: %s', $throwable->getMessage()), $throwable->getCode(), $throwable);
        }

        if (!$response->successful()) {
            File::delete($tmpZip);

            throw new AddonInstallException(sprintf('Download failed (HTTP %d): %s', $response->status(), $this->url));
        }

        $size = is_file($tmpZip) ? (int) filesize($tmpZip) : 0;

        if ($size === 0) {
            File::delete($tmpZip);

            throw new AddonInstallException(sprintf('Downloaded an empty file from: %s', $this->url));
        }

        $maxBytes = (int) config('addons.max_download_bytes', 0);

        if ($maxBytes > 0 && $size > $maxBytes) {
            File::delete($tmpZip);

            throw new AddonInstallException(sprintf('Downloaded archive exceeds the maximum allowed size (%d bytes): %s', $maxBytes, $this->url));
        }

        // Verify the ZIP magic bytes before handing off. A redirected HTML/JSON
        // body is rejected here, against the source URL — never leaking the
        // internal staging path through ZipSource's open error.
        if (!$this->looksLikeZip($tmpZip)) {
            File::delete($tmpZip);

            throw new AddonInstallException(sprintf('Downloaded file is not a valid zip archive: %s', $this->url));
        }

        // TODO (MVP): verify a checksum/signature on the downloaded archive before
        // extraction. Without it a MITM/compromised host can ship arbitrary code
        // that runs on the next boot. Require a sha256 in the install payload and
        // assert hash_equals() here.
        $root = new ZipSource($tmpZip)->fetch($stagingDir);

        File::delete($tmpZip);

        return $root;
    }

    /**
     * Check the first bytes of a file against the ZIP local-file-header magic.
     */
    private function looksLikeZip(string $path): bool
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $magic = (string) fread($handle, 4);
        fclose($handle);

        // PK\x03\x04 normal, PK\x05\x06 empty archive, PK\x07\x08 spanned.
        return in_array($magic, ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"], true);
    }
}
