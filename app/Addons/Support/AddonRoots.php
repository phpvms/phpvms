<?php

declare(strict_types=1);

namespace App\Addons\Support;

use Illuminate\Support\Facades\File;

/**
 * The directories an addon can live in, and the rules that follow from which
 * one it is in.
 *
 * - The IMAGE root (`addons.paths.base`, `modules/`) ships inside the
 *   deployment image. It is read-only at runtime: install, update and file
 *   removal never touch it, because a container cannot persist a write there.
 *   Addons here are updated by deploying a new image, and can only be
 *   enabled/disabled from the admin UI.
 * - The INSTALLED root (`addons.paths.installed`) is a persistent writable
 *   volume. Everything the addon manager places at runtime goes here, and the
 *   full lifecycle (install/update/delete) applies.
 * - The THEMES root (`addons.paths.themes`) is the installed root for addons
 *   of type "theme". Same volume and same rules as the installed root.
 *
 * Both are read from config on every call rather than cached, so tests that
 * point either root at a temp directory behave as expected.
 */
final class AddonRoots
{
    /**
     * The image root: addons shipped with the deployment.
     */
    public static function image(): string
    {
        return rtrim((string) config('addons.paths.base'), '/');
    }

    /**
     * The writable root: addons installed at runtime.
     */
    public static function installed(): string
    {
        return rtrim((string) config('addons.paths.installed', storage_path('addons')), '/');
    }

    /**
     * The writable root for installed addons of type "theme".
     */
    public static function themes(): string
    {
        return rtrim((string) config('addons.paths.themes', storage_path('addon-themes')), '/');
    }

    /**
     * The writable root an addon of $type is installed into.
     */
    public static function installedFor(string $type): string
    {
        return $type === 'theme' ? self::themes() : self::installed();
    }

    /**
     * Every writable root: the ones install/update place files in, and the
     * only ones delete may remove files from.
     *
     * @return list<string>
     */
    public static function writable(): array
    {
        return array_values(array_unique([self::installed(), self::themes()]));
    }

    /**
     * Every root to scan, image root first.
     *
     * When the same addon is present in more than one root, the higher version
     * wins (AddonDiscoveryService::scanAllRoots()); ties and unversioned copies
     * go to the image.
     *
     * Duplicates are removed so a deployment that points two keys at the same
     * directory (the pre-split layout, and what several tests do) scans it once
     * instead of reporting every addon twice.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return array_values(array_unique([self::image(), ...self::writable()]));
    }

    /**
     * Whether $path belongs to an addon that ships in the deployment image,
     * i.e. one the addon manager must not write to or remove.
     *
     * Resolving matters here. `AddonDiscoveryService::scanLocation()` stores
     * each addon's realpath() (AddonDiscoveryService.php:402), so a symlinked
     * module — the standard local dev workflow, e.g.
     * `modules/phpvms-acars -> ../../acars-plugin` — is recorded as its TARGET,
     * which lies outside the image root entirely. A plain prefix test on the
     * stored path therefore calls such a module runtime-managed and lets
     * update() place a shadow copy in the writable root that discovery then
     * ignores in favour of the image one.
     *
     * So two checks: the resolved path is at or under the resolved image root,
     * or it is what one of the image root's direct children resolves to. The
     * second is what "the symlink lives in the image root" actually means.
     */
    public static function isImageManaged(string $path): bool
    {
        $image = self::image();

        if ($image === '' || $path === '') {
            return false;
        }

        $target = realpath($path) ?: rtrim($path, '/');
        $root = realpath($image) ?: $image;

        if ($target === $root || str_starts_with($target, $root.'/')) {
            return true;
        }

        if (!is_dir($image)) {
            return false;
        }

        // Symlinked modules: compare what each direct child resolves to.
        foreach (File::directories($image) as $child) {
            if ((realpath($child) ?: $child) === $target) {
                return true;
            }
        }

        return false;
    }
}
