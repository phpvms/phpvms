<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Models\SimBriefAttempt;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class SimBriefAttemptData extends Data
{
    public function __construct(
        public string $staticId,
        public string $flightId,
        public int $aircraftId,
        public string $expiresAt,
        public string $state,
    ) {}

    public static function fromModel(SimBriefAttempt $attempt): self
    {
        return new self(
            staticId: $attempt->static_id,
            flightId: $attempt->flight_id,
            aircraftId: $attempt->aircraft_id,
            expiresAt: $attempt->expires_at->toIso8601String(),
            state: 'planning',
        );
    }
}
