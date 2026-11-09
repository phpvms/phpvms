<?php

declare(strict_types=1);

namespace App\Features\Maps;

use Illuminate\Container\Attributes\Singleton;
use RuntimeException;

/**
 * Reads a map file and returns the markup inside its root `<svg>`, so a Blade
 * component can wrap it with its own attributes. Files are static, so the
 * contents are memoised per instance.
 */
#[Singleton]
class MapSvgLoader
{
    /** @var array<string, string> */
    private array $inner = [];

    public function __construct(private readonly MapCatalog $catalog) {}

    public function inner(MapData $map): string
    {
        $file = $map->file();
        if (isset($this->inner[$file])) {
            return $this->inner[$file];
        }

        $path = $this->catalog->directory().'/'.$file;
        $svg = is_file($path) ? file_get_contents($path) : false;
        if ($svg === false) {
            throw new RuntimeException("map file not found at {$path}");
        }

        $start = strpos($svg, '>');
        $end = strrpos($svg, '</svg>');
        if ($start === false || $end === false) {
            throw new RuntimeException("map file {$path} is not an svg");
        }

        return $this->inner[$file] = trim(substr($svg, $start + 1, $end - $start - 1));
    }
}
