<?php

declare(strict_types=1);

namespace App\Support\Casts;

use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

/**
 * SimBrief's JSON renders an empty XML node as [] rather than ''. For a scalar
 * field that is an absent value, so it becomes '' like the empty strings
 * SimBrief sends elsewhere.
 */
class EmptyNodeAsStringCast implements Cast
{
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
    {
        return $value === [] ? '' : $value;
    }
}
