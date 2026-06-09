<?php

declare(strict_types=1);

namespace App\Addons\Sources;

use App\Exceptions\AddonInstallException;
use Illuminate\Support\Facades\File;
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

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            // Zip-slip guard: reject absolute paths and parent-traversal.
            if (str_starts_with($name, '/') || str_contains($name, '..')) {
                $zip->close();
                File::deleteDirectory($dest);

                throw new AddonInstallException(sprintf('Unsafe zip entry: %s', $name));
            }
        }

        $zip->extractTo($dest);
        $zip->close();

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
