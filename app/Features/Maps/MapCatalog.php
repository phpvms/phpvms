<?php

declare(strict_types=1);

namespace App\Features\Maps;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Lookup over resources/maps/index.json. The index is read once per instance
 * and never changed afterwards, so the singleton is safe under Octane.
 */
#[Singleton]
class MapCatalog
{
    private readonly string $directory;

    /** @var array<string, MapData>|null */
    private ?array $maps = null;

    /** @var array<string, string>|null alias => map id */
    private ?array $aliases = null;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? resource_path('maps');
    }

    public function directory(): string
    {
        return $this->directory;
    }

    public function find(string $id): ?MapData
    {
        return $this->maps()[$id] ?? null;
    }

    public function world(): MapData
    {
        return $this->maps()['world'] ?? throw new RuntimeException('maps index has no world map');
    }

    public function forIso(?string $iso2): ?MapData
    {
        if ($iso2 === null || trim($iso2) === '') {
            return null;
        }

        return $this->find('country:'.strtolower(trim($iso2)));
    }

    /** Matches a free-text country name case- and accent-insensitively. */
    public function forCountryName(?string $name): ?MapData
    {
        if ($name === null) {
            return null;
        }

        $key = self::normalizeName($name);
        if ($key === '') {
            return null;
        }

        $id = $this->aliases()[$key] ?? null;

        return $id === null ? null : $this->find($id);
    }

    public function continentFor(MapData $country): ?MapData
    {
        if ($country->continent === null) {
            return null;
        }

        return $this->find('continent:'.$country->continent);
    }

    /**
     * Continent maps, tightest bounds first.
     *
     * @return list<MapData>
     */
    public function continents(): array
    {
        $continents = array_values(array_filter(
            $this->maps(),
            static fn (MapData $map): bool => $map->type === 'continent',
        ));
        usort($continents, static fn (MapData $a, MapData $b): int => $a->boundsArea() <=> $b->boundsArea());

        return $continents;
    }

    public static function normalizeName(string $name): string
    {
        $name = Str::ascii(mb_strtolower(trim($name)));
        $name = preg_replace("/[^a-z0-9\s.,()'-]/", '', $name) ?? '';

        return trim(preg_replace('/\s+/', ' ', $name) ?? '');
    }

    /**
     * @return array<string, MapData>
     */
    private function maps(): array
    {
        if ($this->maps !== null) {
            return $this->maps;
        }

        $path = $this->directory.'/index.json';
        $json = is_file($path) ? file_get_contents($path) : false;
        if ($json === false) {
            throw new RuntimeException("maps index not found at {$path}");
        }

        /** @var array<string, array<string, mixed>> $rows */
        $rows = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        $maps = [];
        foreach ($rows as $id => $row) {
            $maps[$id] = MapData::fromIndex($id, $row);
        }

        return $this->maps = $maps;
    }

    /**
     * @return array<string, string>
     */
    private function aliases(): array
    {
        if ($this->aliases !== null) {
            return $this->aliases;
        }

        $aliases = [];
        foreach ($this->maps() as $id => $map) {
            foreach ($map->aliases as $alias) {
                $aliases[$alias] ??= $id;
            }
        }

        return $this->aliases = $aliases;
    }
}
