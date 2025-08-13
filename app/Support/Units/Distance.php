<?php

namespace App\Support\Units;

use App\Contracts\Unit;
use PhpUnitsOfMeasure\Exception\NonNumericValue;
use PhpUnitsOfMeasure\Exception\NonStringUnitName;
use PhpUnitsOfMeasure\PhysicalQuantity\Length;

class Distance extends Unit
{
    public array $responseUnits = [
        'm',
        'km',
        'mi',
        'nmi',
    ];

    /**
     * Distance constructor.
     *
     * @param Distance|float $value
     * @param string         $unit  The unit of $value
     *
     * @throws NonNumericValue
     * @throws NonStringUnitName
     */
    public function __construct(mixed $value, string $unit)
    {
        if (empty($value)) {
            $value = 0;
        }

        $this->localUnit = setting('units.distance');
        $this->internalUnit = config('phpvms.internal_units.distance');

        if ($value instanceof self) {
            $value->toUnit($unit);
            $this->instance = $value->instance;
        } else {
            $this->instance = new Length($value, $unit);
        }
    }
}
