<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Models\Aircraft;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class EligibleAircraftData extends Data
{
    public function __construct(
        public int $id,
        public string $registration,
        public string $icaoType,
        public ?string $name,
        public int $subfleetId,
        public string $subfleetName,
        public ?AirportRefData $airport,
        public string $state,
        public string $status,
    ) {}

    public static function fromModel(Aircraft $aircraft): self
    {
        return new self(
            id: $aircraft->id,
            registration: $aircraft->registration,
            icaoType: $aircraft->icao,
            name: $aircraft->name,
            subfleetId: $aircraft->subfleet_id,
            subfleetName: $aircraft->subfleet->name,
            airport: AirportRefData::fromModel($aircraft->airport),
            state: $aircraft->state->getLabel(),
            status: $aircraft->status->getLabel(),
        );
    }
}
