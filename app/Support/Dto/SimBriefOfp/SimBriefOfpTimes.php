<?php

namespace App\Support\Dto\SimBriefOfp;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Dto;

final class SimBriefOfpTimes extends Dto
{
    public function __construct(
        public string $est_time_enroute,
        public string $sched_time_enroute,
        public CarbonImmutable $sched_out,
        #[Date]
        public CarbonImmutable $sched_off,
        #[Date]
        public CarbonImmutable $sched_on,
        #[Date]
        public CarbonImmutable $sched_in,
        public string $sched_block,
        #[Date]
        public CarbonImmutable $est_out,
        #[Date]
        public CarbonImmutable $est_off,
        #[Date]
        public CarbonImmutable $est_on,
        #[Date]
        public CarbonImmutable $est_in,
        public string $est_block,
        public string $orig_timezone,
        public string $dest_timezone,
        public string $taxi_out,
        public string $taxi_in,
        public string $reserve_time,
        public string $endurance,
        public string $contfuel_time,
        public string $etopsfuel_time,
        public string $extrafuel_time
    ) {}
}
