<?php

namespace App\Support\Casts;

use Carbon\CarbonImmutable;
use Exception;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

class CarbonImmutableOrFalseCast implements Cast
{
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
    {
        // If the API sent literal false, return it immediately
        if ($value === false || $value === 'false') {
            return false;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Exception $e) {
            // Fallback: if it's an empty string or invalid, decide if you want false or an error
            return false;
        }
    }
}
