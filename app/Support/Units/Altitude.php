<?php

namespace App\Support\Units;

use App\Contracts\Unit;
use PhpUnitsOfMeasure\Exception\NonNumericValue;
use PhpUnitsOfMeasure\Exception\NonStringUnitName;
use PhpUnitsOfMeasure\PhysicalQuantity\Length;

class Altitude extends Unit
{
    public array $responseUnits = [
        'ft',
        'km',
        'm',
    ];

    /**
     * @param float $value
     *
     * @throws NonNumericValue
     * @throws NonStringUnitName
     */
    public function __construct($value, string $unit)
    {
        if (empty($value)) {
            $value = 0;
        }

        $this->localUnit = setting('units.altitude');
        $this->internalUnit = config('phpvms.internal_units.altitude');

        if ($value instanceof self) {
            $value->toUnit($unit);
            $this->instance = $value->instance;
        } else {
            $this->instance = new Length($value, $unit);
        }
    }
}
