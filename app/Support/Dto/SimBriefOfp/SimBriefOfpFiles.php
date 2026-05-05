<?php

declare(strict_types=1);

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpFiles extends Dto
{
    /**
     * @param SimBriefOfpFile[] $file
     */
    public function __construct(
        public string $directory,
        public SimBriefOfpFile $pdf,
        public array $file
    ) {}
}
