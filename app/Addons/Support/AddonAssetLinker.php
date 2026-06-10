<?php

declare(strict_types=1);

namespace App\Addons\Support;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;

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
        return new self((string) config('addons.paths.assets', public_path('ext')));
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

        if (!symlink($source, $target)) {
            throw new RuntimeException('Failed to create addon asset symlink: '.$target);
        }
    }

    /**
     * Remove the symlink for an addon if present.
     */
    public function unlink(string $name): void
    {
        $target = $this->target($name);

        if (is_link($target) || is_file($target)) {
            if (!unlink($target)) {
                throw new RuntimeException('Failed to remove addon asset link: '.$target);
            }
        } elseif (is_dir($target)) {
            if (!File::deleteDirectory($target)) {
                throw new RuntimeException('Failed to remove addon asset directory: '.$target);
            }
        }
    }

    /**
     * Absolute path of the public symlink for an addon.
     */
    private function target(string $name): string
    {
        return $this->assetsBase.'/'.$this->normalizeName($name);
    }

    /**
     * Reduce an addon name to a single safe path segment, rejecting any value
     * that could escape the assets base (path traversal / separators).
     */
    private function normalizeName(string $name): string
    {
        $normalized = str_replace('\\', '/', $name);
        $safe = basename($normalized);

        if (in_array($safe, ['', '.', '..'], true) || $safe !== $normalized) {
            throw new InvalidArgumentException('Invalid addon name for asset link target.');
        }

        return $safe;
    }
}
