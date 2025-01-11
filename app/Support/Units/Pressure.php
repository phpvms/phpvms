<?php

namespace App\Support\Units;

use App\Contracts\Unit;
use PhpUnitsOfMeasure\PhysicalQuantity\Pressure as PressureUnit;

/**
 * Composition for the converter
 */
class Pressure extends Unit
{
    public array $responseUnits = [
        'atm',
        'hPa',
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

        $this->localUnit = setting('units.temperature');
        $this->internalUnit = config('phpvms.internal_units.temperature');

        if ($value instanceof self) {
            $value->toUnit($unit);
            $this->instance = $value->instance;
        } else {
            $this->instance = new PressureUnit($value, $unit);
        }
    }
}
