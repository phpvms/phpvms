<?php

declare(strict_types=1);

namespace App\Features\Maps;

/**
 * What one `<x-map.svg>` shows: the map, its viewBox, the pins and route lines
 * in viewBox units, and the dot grid. With `fit`, the viewBox is cropped to
 * the pins and routes (with room around them) at the given aspect ratio, and
 * the dots and pins are scaled with the crop so they keep their size on
 * screen. The dots themselves are laid in the browser (airside's mapDots).
 */
final readonly class MapView
{
    /** Dot grid in viewBox units of the whole map (the longer side is 1000). */
    public const float DOT_GAP = 14;

    public const float DOT_RADIUS = 3.6;

    /**
     * @param array{float, float, float, float}               $viewBox   x, y, width, height
     * @param list<array{x: float, y: float, label: ?string}> $points
     * @param list<list<array{x: float, y: float}>>           $routeRuns
     */
    public function __construct(
        public MapData $map,
        public array $viewBox,
        public float $scale,
        public array $points,
        public array $routeRuns,
    ) {}

    /**
     * @param list<array{lat?: ?float, lon?: ?float, label?: ?string}>              $pins
     * @param list<array{array{0: ?float, 1: ?float}, array{0: ?float, 1: ?float}}> $routes each [[lat, lon], [lat, lon]]
     */
    public static function make(MapData $map, array $pins = [], array $routes = [], bool $fit = false, float $fitAspect = 4 / 3): self
    {
        $points = [];
        foreach ($pins as $pin) {
            if (($pin['lat'] ?? null) === null || ($pin['lon'] ?? null) === null) {
                continue;
            }

            $points[] = [...MapProjection::point($map, (float) $pin['lat'], (float) $pin['lon']), 'label' => $pin['label'] ?? null];
        }

        $routeRuns = [];
        foreach ($routes as [$from, $to]) {
            if (in_array(null, [$from[0] ?? null, $from[1] ?? null, $to[0] ?? null, $to[1] ?? null], true)) {
                continue;
            }

            array_push($routeRuns, ...MapProjection::greatCircle($map, (float) $from[0], (float) $from[1], (float) $to[0], (float) $to[1]));
        }

        $viewBox = [0.0, 0.0, $map->width, $map->height];
        $scale = 1.0;

        if ($fit && $points !== []) {
            $framed = array_merge($points, ...$routeRuns);
            $xs = array_column($framed, 'x');
            $ys = array_column($framed, 'y');
            $span = max($map->width, $map->height);
            $width = max((max($xs) - min($xs)) * 1.6, $span * 0.12);
            $height = max((max($ys) - min($ys)) * 1.6, $span * 0.12);
            $width = max($width, $height * $fitAspect);
            $height = $width / $fitAspect;
            $x = (min($xs) + max($xs) - $width) / 2;
            $y = (min($ys) + max($ys) - $height) / 2;
            // Keep the crop on the map where it fits.
            $x = $width >= $map->width ? ($map->width - $width) / 2 : min(max($x, 0), $map->width - $width);
            $y = $height >= $map->height ? ($map->height - $height) / 2 : min(max($y, 0), $map->height - $height);
            $scale = $width / $map->width;
            $viewBox = [$x, $y, $width, $height];
        }

        return new self($map, $viewBox, $scale, $points, $routeRuns);
    }

    public function dotGap(): float
    {
        return self::DOT_GAP * $this->scale;
    }

    public function dotRadius(): float
    {
        return self::DOT_RADIUS * $this->scale;
    }
}
