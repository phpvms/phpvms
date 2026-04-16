<?php

namespace App\Support\Dto\SimBriefOfp;

use App\Support\Casts\CarbonImmutableOrFalseCast;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Dto;

final class SimBriefOfpNotam extends Dto
{
    public function __construct(
        public string $source_id,
        public string $account_id,
        public string $notam_id,
        public string $location_id,
        public string $location_icao,
        public string $location_name,
        public string $location_type,
        #[Date]
        public CarbonImmutable $date_created,
        #[Date]
        public CarbonImmutable $date_effective,
        #[WithCast(CarbonImmutableOrFalseCast::class)]
        public bool|CarbonImmutable $date_expire,
        public bool $date_expire_is_estimated,
        #[Date]
        public CarbonImmutable $date_modified,
        public string $notam_schedule,
        public string $notam_html,
        public string $notam_text,
        public string $notam_raw,
        public string $notam_nrc,
        public string $notam_qcode,
        public string $notam_qcode_category,
        public string $notam_qcode_subject,
        public string $notam_qcode_status,
        public bool $notam_is_obstacle
    ) {}
}
