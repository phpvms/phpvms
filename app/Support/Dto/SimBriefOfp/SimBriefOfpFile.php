<?php

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpFile extends Dto
{
    public function __construct(public string $name, public string $link) {}
}
