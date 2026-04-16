<?php

namespace App\Support\Units;

use App\Contracts\Unit;
use PhpUnitsOfMeasure\Exception\NonNumericValue;
use PhpUnitsOfMeasure\Exception\NonStringUnitName;
use PhpUnitsOfMeasure\PhysicalQuantity\Temperature as TemperatureUnit;

/**
 * Composition for the converter
 */
class Temperature extends Unit
{
    public array $responseUnits = [
        'C',
        'F',
    ];

    /**
     * @param float|self $value
     *
     * @throws NonNumericValue
     * @throws NonStringUnitName
     */
    public function __construct(mixed $value, string $unit)
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
            $this->instance = new TemperatureUnit($value, $unit);
        }
    }
}
