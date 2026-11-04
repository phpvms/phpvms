<?php

declare(strict_types=1);

namespace App\Features\Maps;

/**
 * One entry of resources/maps/index.json. A plain readonly value object, not a
 * laravel-data class: it never reaches the SPA, so it needs no TypeScript type.
 */
final readonly class MapData
{
    /**
     * @param list<string> $aliases
     * @param list<string> $regions ids (without the `country:` prefix) of the region maps of a country that has them
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $name,
        public ?string $iso2,
        public ?string $continent,
        public float $width,
        public float $height,
        public float $lonMin,
        public float $lonMax,
        public float $latMin,
        public float $latMax,
        public float $k,
        public float $cosLat0,
        public int $lonShift,
        public int $lonWrap,
        public array $aliases = [],
        public ?string $parent = null,
        public array $regions = [],
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromIndex(string $id, array $row): self
    {
        return new self(
            id: $id,
            type: (string) $row['type'],
            name: (string) $row['name'],
            iso2: isset($row['iso2']) ? (string) $row['iso2'] : null,
            continent: isset($row['continent']) ? (string) $row['continent'] : null,
            width: (float) $row['width'],
            height: (float) $row['height'],
            lonMin: (float) $row['lonMin'],
            lonMax: (float) $row['lonMax'],
            latMin: (float) $row['latMin'],
            latMax: (float) $row['latMax'],
            k: (float) $row['k'],
            cosLat0: (float) $row['cosLat0'],
            lonShift: (int) $row['lonShift'],
            lonWrap: (int) $row['lonWrap'],
            aliases: array_values(array_map(strval(...), $row['aliases'] ?? [])),
            parent: isset($row['parent']) ? (string) $row['parent'] : null,
            regions: array_values(array_map(strval(...), $row['regions'] ?? [])),
        );
    }

    /** Path of the SVG file, relative to resources/maps. */
    public function file(): string
    {
        return match ($this->type) {
            'continent' => 'continents/'.$this->continent.'.svg',
            'country'   => 'countries/'.substr($this->id, strlen('country:')).'.svg',
            default     => 'world.svg',
        };
    }

    /** Whether the point falls inside the map's view bounds. */
    public function contains(float $lat, float $lon): bool
    {
        $lon = MapProjection::normalizeLon($this, $lon);

        return $lat >= $this->latMin && $lat <= $this->latMax
            && $lon >= $this->lonMin && $lon <= $this->lonMax;
    }

    /** Area of the view bounds in square degrees, used to prefer the tightest continent. */
    public function boundsArea(): float
    {
        return ($this->lonMax - $this->lonMin) * ($this->latMax - $this->latMin);
    }
}
