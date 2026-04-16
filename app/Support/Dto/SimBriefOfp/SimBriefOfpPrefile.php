<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpPrefile extends Dto
{
    public function __construct(
        public SimBriefOfpPrefileNetwork $vatsim,
        public SimBriefOfpPrefileNetwork $ivao,
        public SimBriefOfpPrefileNetwork $pilotedge,
        public SimBriefOfpPrefileNetwork $poscon
    ) {}
}
