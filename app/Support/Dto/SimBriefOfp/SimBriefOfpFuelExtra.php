<?php

declare(strict_types=1);

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpFuelExtra extends Dto
{
    /**
     * @param SimBriefOfpFuelExtraBucket[] $bucket
     */
    public function __construct(public array $bucket) {}
}
