<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpAircraft extends Dto
{
    public function __construct(
        public string $icaocode,
        public string $iatacode,
        public string $base_type,
        public string $list_type,
        public string $icao_code,
        public string $iata_code,
        public string $name,
        public string $engines,
        public string $reg,
        public string $fin,
        public string $selcal,
        public string $equip,
        public string $equip_category,
        public string $equip_navigation,
        public string $equip_transponder,
        public string $fuelfact,
        public string $fuelfactor,
        public int $max_passengers,
        public string $supports_tlr,
        public string $internal_id,
        public string $is_custom
    ) {}
}
