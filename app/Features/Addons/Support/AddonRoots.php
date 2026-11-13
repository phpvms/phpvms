<?php

declare(strict_types=1);

namespace App\Features\Addons\Support;

/**
 * The directories addons are discovered in, and where the bundled ones come from.
 *
 * - The ROOT (`addons.paths.root`, `storage/app/addons`) holds modules and
 *   themes alike: every addon installed at runtime, and a symlink for each
 *   bundled addon.
 * - The BUNDLED source (`addons.paths.bundled`, `modules/`) ships with each
 *   release and is never written to at runtime. It holds the files of the
 *   addons listed in `addons.bundled`, which {@see BundledAddonLinker} links
 *   into the root, and any other addon placed there by hand.
 *
 * Discovery scans both ({@see scanned()}).
 *
 * Both are read from config on every call rather than cached, so tests that
 * point either one at a temp directory behave as expected.
 */
final class AddonRoots
{
    /**
     * The one directory addons are discovered and installed in.
     */
    public static function root(): string
    {
        return rtrim((string) config('addons.paths.root', storage_path('app/addons')), '/');
    }

    /**
     * Where the files of the bundled addons live.
     */
    public static function bundledSource(): string
    {
        return rtrim((string) config('addons.paths.bundled', base_path('modules')), '/');
    }

    /**
     * The directory names of the bundled addons, in `bundledSource()` and in the root.
     *
     * @return list<string>
     */
    public static function bundled(): array
    {
        return array_values(array_filter((array) config('addons.bundled', []), is_string(...)));
    }

    /**
     * The directories discovery scans, in order, each mapped to the entry
     * names it skips there.
     *
     * The root comes first, so a registry update in the root wins over the
     * copy in modules/. The bundled names are skipped in modules/: they load
     * through their link in the root, and would otherwise load twice.
     *
     * @return array<string, list<string>>
     */
    public static function scanned(): array
    {
        return [
            self::root()          => [],
            self::bundledSource() => self::bundled(),
        ];
    }

    /**
     * Whether $path lies inside the root, after resolving symlinks on both sides.
     *
     * A bundled or dev addon is a symlink in the root, so its resolved path is
     * outside it: this is what keeps a recursive delete away from files that are
     * not ours to remove.
     */
    public static function contains(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        $root = realpath(self::root()) ?: self::root();
        $resolved = realpath($path) ?: rtrim($path, '/');

        return $root !== '' && str_starts_with($resolved, $root.'/');
    }

    /**
     * The entry in the root that holds the addon at $path, or null.
     *
     * Discovery stores each addon's resolved path, so a linked addon's row names
     * its target. This finds the link (or the directory) in the root that
     * resolves to it.
     */
    public static function entryFor(string $path): ?string
    {
        $root = self::root();

        if ($path === '' || !is_dir($root)) {
            return null;
        }

        $target = realpath($path) ?: rtrim($path, '/');

        foreach (scandir($root) ?: [] as $name) {
            if ($name === '.' || $name === '..' || str_starts_with($name, '.')) {
                continue;
            }

            $entry = $root.'/'.$name;

            if ((realpath($entry) ?: $entry) === $target) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Whether the addon at $path is a symlink in the root: a bundled addon, or
     * a dev checkout linked in by hand. Its files are not ours to remove.
     */
    public static function isLinked(string $path): bool
    {
        $entry = self::entryFor($path);

        return $entry !== null && is_link($entry);
    }
}
