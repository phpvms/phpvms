<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Models\Airline;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class AirlineIdentityData extends Data
{
    public function __construct(
        public string $name,
        public string $icao,
        public ?string $iata,
        public ?string $logo,
    ) {}

    public static function fromModel(?Airline $airline): ?self
    {
        return $airline ? new self(
            name: $airline->name,
            icao: $airline->icao,
            iata: $airline->iata,
            logo: $airline->logo_url,
        ) : null;
    }
}
