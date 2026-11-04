<?php

declare(strict_types=1);

namespace App\Features\Maps;

/**
 * Chooses the map that fits a place. The scope is the widest-to-narrowest
 * limit: country falls back to continent, continent falls back to world.
 */
class MapResolver
{
    public const string SCOPE_COUNTRY = 'country';

    public const string SCOPE_CONTINENT = 'continent';

    public const string SCOPE_WORLD = 'world';

    public function __construct(private readonly MapCatalog $catalog) {}

    public function forPoint(?string $countryName, ?float $lat, ?float $lon, string $scope = self::SCOPE_COUNTRY): MapData
    {
        if ($scope === self::SCOPE_WORLD) {
            return $this->catalog->world();
        }

        $country = $this->catalog->forCountryName($countryName);

        if ($scope !== self::SCOPE_CONTINENT && $country instanceof MapData) {
            return $this->regionAt($country, $lat, $lon) ?? $country;
        }

        $continent = $country instanceof MapData
            ? $this->catalog->continentFor($country)
            : $this->continentAt($lat, $lon);

        return $continent ?? $this->catalog->world();
    }

    /** The region map of a country that holds the point, if the country has regions. */
    private function regionAt(MapData $country, ?float $lat, ?float $lon): ?MapData
    {
        if ($lat === null || $lon === null) {
            return null;
        }

        foreach ($country->regions as $id) {
            $region = $this->catalog->find('country:'.$id);
            if ($region instanceof MapData && $region->contains($lat, $lon)) {
                return $region;
            }
        }

        return null;
    }

    private function continentAt(?float $lat, ?float $lon): ?MapData
    {
        if ($lat === null || $lon === null) {
            return null;
        }

        foreach ($this->catalog->continents() as $continent) {
            if ($continent->contains($lat, $lon)) {
                return $continent;
            }
        }

        return null;
    }
}
