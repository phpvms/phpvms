<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpEtopsCriticalPoint extends Dto
{
    public function __construct(
        public string $fix_type,
        public float $pos_lat,
        public float $pos_long,
        public string $elapsed_time,
        public int $est_fob,
        public int $critical_fuel
    ) {}
}
