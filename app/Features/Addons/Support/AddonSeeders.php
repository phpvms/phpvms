<?php

declare(strict_types=1);

namespace App\Features\Addons\Support;

/**
 * Seeder directories addon providers register on boot, keyed by addon root.
 */
final class AddonSeeders
{
    /** @var array<string, string> */
    private static array $directories = [];

    public static function register(string $root, string $directory): void
    {
        self::$directories[self::key($root)] = $directory;
    }

    /**
     * The `*DatabaseSeeder.php` entry points. An addon not booted in this
     * process (just installed or enabled) falls back to the convention path.
     *
     * @return list<string>
     */
    public static function for(string $root): array
    {
        $directory = self::$directories[self::key($root)] ?? $root.'/database/seeders';

        if (!is_dir($directory)) {
            return [];
        }

        return glob($directory.'/*DatabaseSeeder.php') ?: [];
    }

    private static function key(string $root): string
    {
        return rtrim(realpath($root) ?: $root, '/');
    }
}
