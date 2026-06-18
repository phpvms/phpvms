<?php

declare(strict_types=1);

namespace App\Addons\Sources;

use App\Exceptions\AddonInstallException;
use App\Support\Filesystem;
use Illuminate\Support\Facades\File;
use Throwable;
use ZipArchive;

/**
 * Extracts an addon from a local .zip file, guarding against zip-slip, then
 * unwraps a single top-level directory so the returned root holds module.json.
 */
class ZipSource implements AddonSource
{
    public function __construct(
        private readonly string $zipPath,
    ) {}

    public function fetch(string $stagingDir): string
    {
        if (!is_file($this->zipPath)) {
            throw new AddonInstallException(sprintf('Zip not found: %s', $this->zipPath));
        }

        $zip = new ZipArchive();

        if ($zip->open($this->zipPath) !== true) {
            throw new AddonInstallException(sprintf('Cannot open zip: %s', $this->zipPath));
        }

        $dest = $stagingDir.'/'.uniqid('addon_', true);
        File::ensureDirectoryExists($dest);
        $realDest = realpath($dest);

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);

                // Zip-slip guard: reject absolute paths, parent-traversal, and
                // backslash separators (Windows-style escapes).
                if (str_starts_with($name, '/') || str_contains($name, '..') || str_contains($name, '\\')) {
                    throw new AddonInstallException(sprintf('Unsafe zip entry: %s', $name));
                }
            }

            if (!$zip->extractTo($dest)) {
                throw new AddonInstallException(sprintf('Failed to extract zip: %s', $this->zipPath));
            }
        } catch (Throwable $throwable) {
            File::deleteDirectory($dest);

            throw $throwable instanceof AddonInstallException
                ? $throwable
                : new AddonInstallException($throwable->getMessage(), $throwable->getCode(), $throwable);
        } finally {
            $zip->close();
        }

        // Post-extraction guard: every extracted file must resolve inside $dest
        // (defends against symlink entries / normalisation that bypassed the
        // name-based check above).
        if ($realDest !== false) {
            foreach (File::allFiles($dest) as $file) {
                $real = realpath($file->getPathname());

                if ($real === false || !Filesystem::isWithin($realDest, $real)) {
                    File::deleteDirectory($dest);

                    throw new AddonInstallException(sprintf('Zip entry escaped staging directory: %s', $file->getPathname()));
                }
            }
        }

        return $this->resolveRoot($realDest === false ? $dest : $realDest);
    }

    /**
     * If the archive wrapped everything in a single top-level directory and
     * there's no module.json at the root, descend into it.
     */
    private function resolveRoot(string $dest): string
    {
        if (File::exists($dest.'/module.json')) {
            return $dest;
        }

        $dirs = File::directories($dest);
        $files = File::files($dest);

        if (count($dirs) === 1 && count($files) === 0) {
            return $dirs[0];
        }

        return $dest;
    }
}
