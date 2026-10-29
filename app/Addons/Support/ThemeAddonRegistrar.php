<?php

declare(strict_types=1);

namespace App\Addons\Support;

use App\Addons\Models\AddonBootCache;
use Illuminate\Support\Facades\Log;

/**
 * Makes enabled addons of type "theme" selectable as themes.
 *
 * igaster/laravel-theme only scans `themes.themes_path`
 * (resources/views/layouts) for theme.json files, but it also merges every
 * entry in `config('themes.themes')` when it scans in its own boot(). So this
 * runs in AddonServiceProvider::register() — every register() runs before any
 * boot() — and writes one config entry per theme addon, read from the
 * DB-free boot cache.
 *
 * An addon theme is a sealed package:
 *  - views:  {addon}/resources/views, then the core resources/views (the
 *            Laravel paths igaster always appends). No local override layer.
 *  - assets: {addon}/public, served from public/storage/themes/{name}
 *            (see ThemeAssetLinker).
 *  - theme.json at the addon root: the same keys as a folder theme
 *            (name, kind, manifest, ...), loaded as theme settings.
 */
class ThemeAddonRegistrar
{
    public function __construct(private readonly BootCache $bootCache) {}

    /**
     * Register every enabled theme addon in config('themes.themes').
     */
    public function register(): void
    {
        $this->forgetRegistered();

        foreach ($this->bootCache->enabled() as $entry) {
            if ($entry->type !== 'theme') {
                continue;
            }

            $this->registerTheme($entry);
        }
    }

    /**
     * Remove the entries an earlier register() added.
     *
     * `config:cache` boots the app before it writes the cache, so the entries
     * this class adds end up in bootstrap/cache/config.php. Without this, a
     * theme addon disabled or deleted after the cache was built would stay
     * registered until the cache is cleared. The names are recorded under
     * `themes.addon_themes`, which is cached alongside them.
     */
    private function forgetRegistered(): void
    {
        $themes = (array) config('themes.themes', []);

        foreach ((array) config('themes.addon_themes', []) as $name) {
            unset($themes[$name]);
        }

        config(['themes.themes' => $themes, 'themes.addon_themes' => []]);
    }

    /**
     * Whether a theme named $name already exists outside this addon: a folder
     * theme, or an entry in config('themes.themes').
     */
    public static function nameTaken(string $name): bool
    {
        $themesPath = (string) config('themes.themes_path', resource_path('views/layouts'));

        return array_key_exists($name, (array) config('themes.themes', []))
            || is_file($themesPath.'/'.$name.'/theme.json');
    }

    /**
     * The theme name an addon registers under: theme.json `name`, else the
     * addon name.
     */
    public static function themeName(string $addonPath, string $addonName): string
    {
        $name = self::themeJson($addonPath)['name'] ?? null;

        return is_string($name) && $name !== '' ? $name : $addonName;
    }

    /**
     * The decoded theme.json at the addon root; empty when absent or invalid.
     *
     * @return array<string, mixed>
     */
    public static function themeJson(string $addonPath): array
    {
        $file = $addonPath.'/theme.json';

        if (!is_file($file)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? $data : [];
    }

    private function registerTheme(AddonBootCache $entry): void
    {
        $views = realpath($entry->path.'/resources/views');

        if ($views === false) {
            Log::warning(sprintf('ThemeAddonRegistrar: theme addon "%s" has no resources/views directory', $entry->name));

            return;
        }

        $name = self::themeName($entry->path, $entry->name);

        // igaster merges a config entry over a folder theme of the same name,
        // so registering under a taken name would silently repoint that theme
        // (e.g. "seven") at the addon's files. Refuse it instead. Install and
        // update already refuse a taken name; this covers an addon dropped on
        // disk by hand.
        if (self::nameTaken($name)) {
            Log::warning(sprintf(
                'ThemeAddonRegistrar: not registering theme addon "%s" — a theme named "%s" already exists',
                $entry->name,
                $name,
            ));

            return;
        }

        $themesPath = (string) config('themes.themes_path', resource_path('views/layouts'));

        // Set as a whole array, not via a dotted key: a theme name may itself
        // contain a dot.
        $themes = (array) config('themes.themes', []);
        $themes[$name] = [
            ...self::themeJson($entry->path),
            'name' => $name,
            // igaster resolves a views-path relative to themes_path (one with a
            // leading slash is relative to base_path, not absolute), so the
            // addon directory is expressed as a path from there.
            'views-path' => ThemeAssetLinker::relativePath(realpath($themesPath) ?: $themesPath, $views),
            'asset-path' => ThemeAssetLinker::webPath($name),
        ];

        config([
            'themes.themes'       => $themes,
            'themes.addon_themes' => [...(array) config('themes.addon_themes', []), $name],
        ]);
    }
}
