<?php

declare(strict_types=1);

namespace App\Support\Dto\PhpvmsApi;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

class AirportData extends Data
{
    public function __construct(
        public string $icao,
        public string $iata,
        public string $name,
        #[MapInputName('city')]
        public string $location,
        public string $country,
        public string $region,
        #[MapInputName('tz')]
        public string $timezone,
        public int $elevation,
        public float $lat,
        public float $lon,
        /**
         * The id the catalog keeps this airport under -- a generated value,
         * not a code. Nullable and last because an older api_url may not
         * return one; `AirportObserver` then seeds the id from the ICAO as
         * before.
         */
        public ?string $id = null,
    ) {}
}
