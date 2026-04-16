<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpWeather extends Dto
{
    /**
     * @param string[] $altn_metar
     * @param string[] $altn_taf
     * @param string[] $etops_metar
     * @param string[] $etops_taf
     */
    public function __construct(
        public string $orig_metar,
        public string $orig_taf,
        public string $dest_metar,
        public string $dest_taf,
        public array $altn_metar,
        public array $altn_taf,
        public string $toaltn_metar,
        public string $toaltn_taf,
        public string $eualtn_metar,
        public string $eualtn_taf,
        public array $etops_metar,
        public array $etops_taf
    ) {}
}
