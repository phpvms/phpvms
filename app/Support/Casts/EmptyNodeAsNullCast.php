<?php

declare(strict_types=1);

namespace App\Support\Casts;

use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

/**
 * A blank SimBrief node ('' or []) on a numeric field, e.g. metar_visibility for a station with no METAR.
 */
class EmptyNodeAsNullCast implements Cast
{
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
    {
        return $value === [] || $value === '' ? null : $value;
    }
}
