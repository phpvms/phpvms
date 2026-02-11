<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpFetch extends Dto
{
    public function __construct(
        public int $userid,
        public string $static_id,
        public string $status,
        public float $time
    ) {}
}
