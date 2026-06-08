<?php

declare(strict_types=1);

namespace App\Addons\Support;

use Illuminate\Support\Facades\File;

/**
 * Manages per-addon public asset symlinks: {addon}/public → public/ext/{name}.
 *
 * Links are created at install/enable and rebuilt by `addons:relink` after a
 * deploy or container restart (ephemeral filesystems drop the symlinks).
 */
class AddonAssetLinker
{
    public function __construct(
        private readonly string $assetsBase,
    ) {}

    /**
     * Resolve the assets base from config when constructed via the container.
     */
    public static function fromConfig(): self
    {
        return new self((string) config('addons.paths.assets', config('addons.paths.assets')));
    }

    /**
     * Symlink {addonPath}/public to {assetsBase}/{name}. No-op when the addon
     * ships no public/ directory. Idempotent: replaces an existing link.
     */
    public function link(string $name, string $addonPath): void
    {
        $source = $addonPath.'/public';

        if (!is_dir($source)) {
            return;
        }

        File::ensureDirectoryExists($this->assetsBase);

        $target = $this->target($name);

        $this->unlink($name);

        symlink($source, $target);
    }

    /**
     * Remove the symlink for an addon if present.
     */
    public function unlink(string $name): void
    {
        $target = $this->target($name);

        if (is_link($target) || file_exists($target)) {
            @unlink($target);
        }
    }

    /**
     * Absolute path of the public symlink for an addon.
     */
    private function target(string $name): string
    {
        return $this->assetsBase.'/'.$name;
    }
}
