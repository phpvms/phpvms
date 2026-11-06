<?php

declare(strict_types=1);

namespace Modules\PhpvmsAirsideTheme\Support;

/**
 * The Meteocons the theme build wrote to `build/meteocons/<name>.svg`.
 *
 * The folder is listed once per process, so rendering an icon costs no
 * filesystem call. An empty folder (no build yet) is not remembered, so the
 * icons show up as soon as a build or the dev server writes them.
 */
final class Meteocons
{
    public const string FALLBACK = 'not-available-fill';

    /** @var array<string, true>|null */
    private static ?array $available = null;

    /**
     * The icon to render for a name: the name itself, the fallback when the name is unknown, or null when the build has no icons.
     */
    public static function resolve(mixed $name): ?string
    {
        $available = self::available();

        if (is_string($name) && isset($available[$name])) {
            return $name;
        }

        return isset($available[self::FALLBACK]) ? self::FALLBACK : null;
    }

    public static function url(string $name): string
    {
        return asset(AirsideVite::BUILD_DIRECTORY.'/meteocons/'.$name.'.svg');
    }

    /**
     * @return array<string, true>
     */
    private static function available(): array
    {
        if (self::$available !== null) {
            return self::$available;
        }

        $names = [];
        foreach (glob(public_path(AirsideVite::BUILD_DIRECTORY.'/meteocons/*.svg')) ?: [] as $file) {
            $names[basename($file, '.svg')] = true;
        }

        if ($names !== []) {
            self::$available = $names;
        }

        return $names;
    }
}
