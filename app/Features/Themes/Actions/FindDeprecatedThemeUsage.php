<?php

declare(strict_types=1);

namespace App\Features\Themes\Actions;

use Igaster\LaravelTheme\Facades\Theme;
use Symfony\Component\Finder\Finder;

/**
 * Which 7.x shims a theme's own views still use, so the admin can be told to upgrade it.
 */
final class FindDeprecatedThemeUsage
{
    // Shown name => what a view uses it by.
    private const array PATTERNS = [
        'App\Models\Enums' => '/App\\\\Models\\\\Enums\\\\/',
        '$moduleSvc'       => '/\$moduleSvc\b/',
        '@ability'         => '/@ability\s*\(/',
        '$simbrief->xml'   => '/->xml\b/',
    ];

    /**
     * @return list<string> the shims used, in PATTERNS order
     */
    public function __invoke(string $theme): array
    {
        if ($theme === '' || !Theme::exists($theme)) {
            return [];
        }

        $paths = array_filter(Theme::find($theme)->getViewPaths(), is_dir(...));
        if ($paths === []) {
            return [];
        }

        $found = [];
        foreach (Finder::create()->files()->name('*.php')->in($paths) as $file) {
            $source = $file->getContents();

            foreach (self::PATTERNS as $name => $pattern) {
                if (!isset($found[$name]) && preg_match($pattern, $source) === 1) {
                    $found[$name] = true;
                }
            }

            if (count($found) === count(self::PATTERNS)) {
                break;
            }
        }

        return array_values(array_filter(array_keys(self::PATTERNS), fn (string $name): bool => isset($found[$name])));
    }
}
