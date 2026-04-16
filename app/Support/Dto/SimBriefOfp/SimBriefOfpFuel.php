<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpFuel extends Dto
{
    public function __construct(
        public int $taxi,
        public int $enroute_burn,
        public int $contingency,
        public int $alternate_burn,
        public int $reserve,
        public int $etops,
        public int $extra,
        public int $extra_required,
        public int $extra_optional,
        public int $min_takeoff,
        public int $plan_takeoff,
        public int $plan_ramp,
        public int $plan_landing,
        public int $avg_fuel_flow,
        public int $max_tanks
    ) {}
}
