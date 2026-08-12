<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Models\Subfleet;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class EligibleSubfleetData extends Data
{
    public function __construct(
        public int $id,
        public ?string $airlineIcao,
        public ?string $airlineName,
        public ?string $icaoType,
        public string $displayName,
        public int $eligibleAircraftCount,
        public bool $disabled,
        public ?string $availabilityLabel,
    ) {}

    public static function fromModel(Subfleet $subfleet, int $eligibleAircraftCount): self
    {
        return new self(
            id: $subfleet->id,
            airlineIcao: $subfleet->airline?->icao,
            airlineName: $subfleet->airline?->name,
            icaoType: filled($subfleet->type) ? $subfleet->type : null,
            displayName: $subfleet->name,
            eligibleAircraftCount: $eligibleAircraftCount,
            disabled: $eligibleAircraftCount === 0,
            availabilityLabel: $eligibleAircraftCount === 0 ? 'None available' : null,
        );
    }
}
