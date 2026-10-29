<?php

declare(strict_types=1);

namespace App\Addons\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Links a theme addon's public/ into the public storage disk:
 * {theme_assets}/{name} -> {addon}/public.
 *
 * Served through `storage:link` (public/storage -> storage/app/public), so the
 * web server reads the files directly and PHP never streams them. The link
 * lives inside the persistent storage volume, so a redeploy does not drop it.
 *
 * The link target is relative. An absolute one breaks when a shared-host
 * install is moved to another directory, and when the host and container see
 * the volume at different paths.
 */
class ThemeAssetLinker
{
    public function __construct(private readonly string $assetsBase) {}

    public static function fromConfig(): self
    {
        return new self(rtrim((string) config('addons.paths.theme_assets', storage_path('app/public/themes')), '/'));
    }

    /**
     * Web path of a theme's assets relative to public/, e.g. "storage/themes/airside".
     * This is the igaster `asset-path` for the theme.
     */
    public static function webPath(string $themeName): string
    {
        $base = (string) config('addons.paths.theme_assets', storage_path('app/public/themes'));
        $disk = storage_path('app/public');

        // A base outside the public disk is not web-served through
        // public/storage at all; keep the default URL rather than build one
        // from an unrelated filesystem path.
        $relative = str_starts_with($base, $disk.'/') ? trim(Str::after($base, $disk), '/\\') : 'themes';

        return 'storage/'.$relative.'/'.AddonAssetLinker::segment($themeName);
    }

    /**
     * Link {addonPath}/public as {assetsBase}/{themeName}. Idempotent: replaces
     * an existing link, and removes it when the theme ships no public/.
     */
    public function link(string $themeName, string $addonPath): void
    {
        // First, so a version that dropped public/ does not leave the previous
        // version's link dangling.
        $this->unlink($themeName);

        $source = realpath($addonPath.'/public');

        if ($source === false || !is_dir($source)) {
            return;
        }

        File::ensureDirectoryExists($this->assetsBase);

        $target = $this->target($themeName);

        $base = realpath($this->assetsBase) ?: $this->assetsBase;

        if (!symlink(self::relativePath($base, $source), $target)) {
            throw new RuntimeException('Failed to create theme asset symlink: '.$target);
        }
    }

    /**
     * Remove the link for a theme if present. Never follows it: only the link
     * itself is removed, not the addon's files behind it.
     */
    public function unlink(string $themeName): void
    {
        $target = $this->target($themeName);

        if ((is_link($target) || is_file($target)) && !unlink($target)) {
            throw new RuntimeException('Failed to remove theme asset link: '.$target);
        }
    }

    /**
     * The path from directory $from to $to, both absolute.
     */
    public static function relativePath(string $from, string $to): string
    {
        $fromParts = array_values(array_filter(explode('/', $from), static fn (string $part): bool => $part !== ''));
        $toParts = array_values(array_filter(explode('/', $to), static fn (string $part): bool => $part !== ''));

        $common = 0;
        $max = min(count($fromParts), count($toParts));

        while ($common < $max && $fromParts[$common] === $toParts[$common]) {
            $common++;
        }

        $up = array_fill(0, count($fromParts) - $common, '..');
        $relative = implode('/', [...$up, ...array_slice($toParts, $common)]);

        return $relative === '' ? '.' : $relative;
    }

    private function target(string $themeName): string
    {
        return $this->assetsBase.'/'.AddonAssetLinker::segment($themeName);
    }
}
