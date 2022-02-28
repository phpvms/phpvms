<?php

namespace App\Support\Units;

use App\Contracts\Unit;
use PhpUnitsOfMeasure\PhysicalQuantity\Length;

class Distance extends Unit
{
    public $responseUnits = [
        'm',
        'km',
        'mi',
        'nmi',
    ];

    /**
     * Distance constructor.
     *
     * @param float  $value
     * @param string $unit
     *
     * @throws \PhpUnitsOfMeasure\Exception\NonNumericValue
     * @throws \PhpUnitsOfMeasure\Exception\NonStringUnitName
     */
    public function __construct($value, string $unit)
    {
        if (empty($value)) {
            $value = 0;
        }

        $this->unit = setting('units.distance');

        if ($value instanceof Distance) {
            $value->toUnit($unit);
            $this->instance = $value;
        } else {
            $this->instance = new Length($value, $unit);
        }
    }
}
