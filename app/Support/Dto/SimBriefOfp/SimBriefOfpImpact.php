<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpImpact extends Dto
{
    public function __construct(
        public string $time_enroute,
        public string $time_difference,
        public int $enroute_burn,
        public string $burn_difference,
        public int $ramp_fuel,
        public int $initial_fl,
        public int $initial_tas,
        public float $initial_mach,
        public int $cost_index
    ) {}
}
