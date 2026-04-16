<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpWindData extends Dto
{
    public function __construct(
        public int $altitude,
        public int $wind_dir,
        public int $wind_spd,
        public int $oat
    ) {}
}
