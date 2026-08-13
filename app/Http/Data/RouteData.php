<?php

declare(strict_types=1);

namespace App\Http\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** Nav-display route: origin -> destination (either may be null). */
#[TypeScript]
final class RouteData extends Data
{
    public function __construct(
        public ?RoutePointData $from,
        public ?RoutePointData $to,
    ) {}
}
