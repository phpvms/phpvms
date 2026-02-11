<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpFuelExtra extends Dto
{
    /**
     * @param SimBriefOfpFuelExtraBucket[] $bucket
     */
    public function __construct(public array $bucket) {}
}
