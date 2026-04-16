<?php

namespace App\Support\Units;

use App\Contracts\Unit;
use PhpUnitsOfMeasure\Exception\NonNumericValue;
use PhpUnitsOfMeasure\Exception\NonStringUnitName;
use PhpUnitsOfMeasure\PhysicalQuantity\Mass as MassUnit;

class Mass extends Unit
{
    public array $responseUnits = [
        'kg',
        'lbs',
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

        $this->localUnit = setting('units.weight');
        $this->internalUnit = config('phpvms.internal_units.mass');

        if ($value instanceof self) {
            $value->toUnit($unit);
            $this->instance = $value->instance;
        } else {
            $this->instance = new MassUnit($value, $unit);
        }
    }
}
