<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpEtops extends Dto
{
    /**
     * @param SimBriefOfpEtopsSuitableAirport[] $suitable_airport
     * @param SimBriefOfpEtopsEqualTimePoint[]  $equal_time_point
     */
    public function __construct(
        public int $rule,
        public SimBriefOfpEtopsEntryExitPoint $entry,
        public SimBriefOfpEtopsEntryExitPoint $exit,
        public array $suitable_airport,
        public array $equal_time_point,
        public SimBriefOfpEtopsCriticalPoint $critical_point
    ) {}
}
