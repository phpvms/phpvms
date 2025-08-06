<?php

namespace App\Support\Units;

use App\Contracts\Unit;
use PhpUnitsOfMeasure\Exception\NonNumericValue;
use PhpUnitsOfMeasure\Exception\NonStringUnitName;
use PhpUnitsOfMeasure\PhysicalQuantity\Volume as VolumeUnit;

/**
 * Wrap the converter class
 */
class Volume extends Unit
{
    public array $responseUnits = [
        'gal',
        'liters',
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

        $this->localUnit = setting('units.volume');
        $this->internalUnit = config('phpvms.internal_units.volume');

        if ($value instanceof self) {
            $value->toUnit($unit);
            $this->instance = $value->instance;
        } else {
            $this->instance = new VolumeUnit($value, $unit);
        }
    }
}
