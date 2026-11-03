<?php

declare(strict_types=1);

namespace App\Support\Casts;

use Carbon\Carbon;
use Exception;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

/**
 * SimBrief's JSON renders an empty XML element as `[]`, so a date it has no
 * value for (e.g. a VATSIM ATIS without an issue time) arrives as an empty
 * array rather than a missing key. Treat that, and anything unparsable, as null.
 */
class CarbonOrNullCast implements Cast
{
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): ?Carbon
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Exception) {
            return null;
        }
    }
}
