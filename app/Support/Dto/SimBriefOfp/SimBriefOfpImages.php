<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpImages extends Dto
{
    /**
     * @param SimBriefOfpFile[] $map
     */
    public function __construct(public string $directory, public array $map) {}
}
