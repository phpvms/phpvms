<?php

declare(strict_types=1);

namespace App\Features\Addons\Support;

/**
 * Resolves an addon manifest's `icon` to something a page can show.
 *
 * The icon is an http(s) URL, used as is, or a path relative to the addon
 * folder. A local icon is served by the `addons.icon` route, and only when it
 * is an image file inside the addon folder.
 */
final class AddonIcon
{
    /** @var list<string> */
    public const array EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];

    /**
     * The icon's URL, or null when there is none to show.
     */
    public static function url(string $name, string $addonPath, ?string $icon): ?string
    {
        if ($icon === null || $icon === '') {
            return null;
        }

        if (preg_match('#^https?://.+#i', $icon) === 1) {
            return $icon;
        }

        if (self::file($addonPath, $icon) === null) {
            return null;
        }

        return route('addons.icon', ['addon' => $name]);
    }

    /**
     * The absolute path of a local icon, or null when the value is not a safe
     * relative path to an image file inside the addon folder.
     */
    public static function file(string $addonPath, ?string $icon): ?string
    {
        // The registry's rule for a local icon: no scheme, not absolute, no `..`.
        if ($icon === null || preg_match('#^(?![a-z][a-z0-9+.-]*:)(?![/\\\\])(?!.*\.\.).+#i', $icon) !== 1) {
            return null;
        }

        $root = realpath($addonPath);
        $file = realpath($addonPath.'/'.$icon);

        if ($root === false || $file === false || !is_file($file) || !str_starts_with($file, $root.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), self::EXTENSIONS, true) ? $file : null;
    }
}
