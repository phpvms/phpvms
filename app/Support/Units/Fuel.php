<?php

namespace App\Support\Units;

use App\Contracts\Unit;
use PhpUnitsOfMeasure\PhysicalQuantity\Mass;

class Fuel extends Unit
{
    public array $responseUnits = [
        'kg',
        'lbs',
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

        $this->localUnit = setting('units.fuel');
        $this->internalUnit = config('phpvms.internal_units.fuel');

        if ($value instanceof self) {
            $value->toUnit($unit);
            $this->instance = $value->instance;
        } else {
            $this->instance = new Mass($value, $unit);
        }
    }
}
