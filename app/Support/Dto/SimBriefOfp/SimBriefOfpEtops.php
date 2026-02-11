<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpEtops extends Dto
{
    /**
     * @param SimbriefOfpEtopsSuitableAirport[] $suitable_airport
     * @param SimbriefOfpEtopsEqualTimePoint[]  $equal_time_point
     */
    public function __construct(
        public int $rule,
        public SimbriefOfpEtopsEntryExitPoint $entry,
        public SimbriefOfpEtopsEntryExitPoint $exit,
        public array $suitable_airport,
        public array $equal_time_point,
        public SimbriefOfpEtopsCriticalPoint $critical_point
    ) {}
}
