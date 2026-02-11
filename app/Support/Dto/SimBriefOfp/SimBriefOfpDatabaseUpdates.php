<?php

namespace App\Support\Dto\SimBriefOfp;

use Carbon\Carbon;
use Spatie\LaravelData\Dto;

final class SimBriefOfpDatabaseUpdates extends Dto
{
    public function __construct(
        public Carbon $metar_taf,
        public Carbon $winds,
        public Carbon $sigwx,
        public Carbon $sigmet,
        public Carbon $notams,
        public Carbon $tracks
    ) {}
}
