<?php

namespace App\Support\Units;

use App\Contracts\Unit;
use PhpUnitsOfMeasure\PhysicalQuantity\Velocity as VelocityUnit;

/**
 * Class Velocity.
 */
class Velocity extends Unit
{
    public array $responseUnits = [
        'km/h',
        'knots',
    ];

    /**
     * @param float $value
     *
     * @throws \PhpUnitsOfMeasure\Exception\NonNumericValue
     * @throws \PhpUnitsOfMeasure\Exception\NonStringUnitName
     */
    public function __construct($value, string $unit)
    {
        if (empty($value)) {
            $value = 0;
        }

        $this->localUnit = setting('units.speed');
        $this->internalUnit = config('phpvms.internal_units.velocity');

        if ($value instanceof self) {
            $value->toUnit($unit);
            $this->instance = $value->instance;
        } else {
            $this->instance = new VelocityUnit($value, $unit);
        }
    }
}
