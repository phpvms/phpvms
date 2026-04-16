<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpWeights extends Dto
{
    public function __construct(
        public int $oew,
        public int $pax_count,
        public int $bag_count,
        public int $pax_count_actual,
        public int $bag_count_actual,
        public int $pax_weight,
        public int $bag_weight,
        public int $freight_added,
        public int $cargo,
        public int $payload,
        public int $est_zfw,
        public int $max_zfw,
        public int $est_tow,
        public int $max_tow,
        public int $max_tow_struct,
        public string $tow_limit_code,
        public int $est_ldw,
        public int $max_ldw,
        public int $est_ramp
    ) {}
}
