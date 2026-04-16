<?php

namespace App\Support\Dto\SimBriefOfp;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Dto;

final class SimBriefOfpParams extends Dto
{
    public function __construct(
        public int $request_id,
        public string $sequence_id,
        public string $static_id,
        public int $user_id,
        #[Date]
        public CarbonImmutable $time_generated,
        public string $xml_file,
        public string $ofp_layout,
        public int $airac,
        public string $units
    ) {}
}
