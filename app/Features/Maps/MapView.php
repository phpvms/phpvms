<?php

declare(strict_types=1);

namespace App\Features\Maps;

/**
 * What one `<x-map.svg>` shows: the map, its viewBox, the pins and route lines
 * in viewBox units, and the dot grid. With `fit`, the viewBox is cropped to
 * the pins and routes (with room around them) at the given aspect ratio, and
 * the dots and pins are scaled with the crop so they keep their size on
 * screen. On a map that spans the whole globe, a route that crosses the
 * antimeridian is joined into one line past the map's edge (and its pins move
 * with it); the land is then drawn again one map width over, as maplibre's
 * world copies do. On a narrower map such a route keeps the whole map. The dots
 * themselves are laid in the browser (airside's mapDots).
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
     * @param bool                                            $wraps     whether the view runs past the map's left or right edge
     */
    public function __construct(
        public MapData $map,
        public array $viewBox,
        public float $scale,
        public array $points,
        public array $routeRuns,
        public bool $wraps = false,
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
        $joined = false;
        $split = false;
        foreach ($routes as [$from, $to]) {
            if (in_array(null, [$from[0] ?? null, $from[1] ?? null, $to[0] ?? null, $to[1] ?? null], true)) {
                continue;
            }

            $runs = MapProjection::greatCircle($map, (float) $from[0], (float) $from[1], (float) $to[0], (float) $to[1]);
            if (count($runs) > 1 && $map->lonMax - $map->lonMin >= 360) {
                $runs = [self::join($runs, $map->width)];
                $joined = true;
            } elseif (count($runs) > 1) {
                $split = true;
            }

            array_push($routeRuns, ...$runs);
        }

        if ($joined) {
            $xs = array_column(array_merge(...$routeRuns), 'x');
            $centre = (min($xs) + max($xs)) / 2;
            foreach ($points as $i => $point) {
                $copies = [$point['x'], $point['x'] - $map->width, $point['x'] + $map->width];
                usort($copies, fn (float $a, float $b): int => abs($a - $centre) <=> abs($b - $centre));
                $points[$i]['x'] = $copies[0];
            }
        }

        $viewBox = [0.0, 0.0, $map->width, $map->height];
        $scale = 1.0;

        if ($fit && $points !== [] && !$split) {
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
            // Keep the crop on the map where it fits. A joined route may run past the left or right edge.
            if (!$joined) {
                $x = $width >= $map->width ? ($map->width - $width) / 2 : min(max($x, 0), $map->width - $width);
            }

            $y = $height >= $map->height ? ($map->height - $height) / 2 : min(max($y, 0), $map->height - $height);
            $scale = $width / $map->width;
            $viewBox = [$x, $y, $width, $height];
        }

        $wraps = $joined && ($viewBox[0] < 0 || $map->width < $viewBox[0] + $viewBox[2]);

        return new self($map, $viewBox, $scale, $points, $routeRuns, $wraps);
    }

    /**
     * Where the land is drawn again so a wrapping view has land past the map's
     * edge: x offsets, one map width to the left and/or right.
     *
     * @return list<float>
     */
    public function landCopies(): array
    {
        if (!$this->wraps) {
            return [];
        }

        [$x, , $width] = $this->viewBox;

        return array_values(array_filter([
            $x < 0 ? -$this->map->width : null,
            $x + $width > $this->map->width ? $this->map->width : null,
        ], fn (?float $offset): bool => $offset !== null));
    }

    /**
     * The runs of a great circle that wraps the map, joined into one line: each
     * run after a wrap is moved one map width over, so it carries on from the last.
     *
     * @param  list<list<array{x: float, y: float}>> $runs
     * @return list<array{x: float, y: float}>
     */
    private static function join(array $runs, float $mapWidth): array
    {
        $line = [];
        $offset = 0.0;
        foreach ($runs as $run) {
            if ($line !== []) {
                $last = end($line)['x'];
                $offset += $run[0]['x'] + $offset < $last ? $mapWidth : -$mapWidth;
            }

            foreach ($run as $point) {
                $line[] = ['x' => $point['x'] + $offset, 'y' => $point['y']];
            }
        }

        return $line;
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
