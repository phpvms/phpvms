<?php

declare(strict_types=1);

namespace App\Support\Dto\SimBriefOfp;

use Spatie\LaravelData\Dto;

final class SimBriefOfpPrefileNetwork extends Dto
{
    public function __construct(
        public string $name,
        public string $site,
        public string $link,
        public string $form
    ) {}
}
