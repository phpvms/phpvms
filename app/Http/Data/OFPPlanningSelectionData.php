<?php

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class OFPPlanningSelectionData extends Data
{
    /** @param array<int, EligibleSubfleetData> $subfleets */
    public function __construct(
        public FlightDetailData $flight,
        public string $dispatchUrl,
        public string $planningUrl,
        public ?string $aircraftAssignmentUrl,
        public array $subfleets,
    ) {}
}
