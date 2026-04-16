<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpFirNotam extends Dto
{
    public function __construct(
        public string $source_id,
        public string $account_id,
        public string $notam_id,
        public int $notam_part,
        public string $cns_location_id,
        public ?string $xoveraccountid,
        public ?string $xovernotamid,
        public string $icao_id,
        public string $icao_name,
        public int $total_parts,
        public int $notam_created_dtg,
        public int $notam_effective_dtg,
        public ?int $notam_expire_dtg,
        public ?int $notam_expire_dtg_estimated,
        public int $notam_lastmod_dtg,
        public int $notam_inserted_dtg,
        public string $notam_text,
        public string $notam_report,
        public ?string $notam_nrc,
        public ?string $notam_qcode
    ) {}
}
