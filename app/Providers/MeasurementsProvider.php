<?php

declare(strict_types=1);

namespace App\Providers;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use PhpUnitsOfMeasure\Exception\NonStringUnitName;
use PhpUnitsOfMeasure\Exception\UnknownUnitOfMeasure;
use PhpUnitsOfMeasure\PhysicalQuantity\Temperature;

/**
 * Add new measurement units to PhpUnitsOfMeasure
 */
class MeasurementsProvider extends ServiceProvider
{
    public function boot(): void
    {
        try {
            $this->addTemperatures();
        } catch (Exception $exception) {
            Log::error('Error adding temperature units: '.$exception->getMessage());
        }
    }

    /**
     * Add lowercase temperature units
     *
     * @throws NonStringUnitName
     * @throws UnknownUnitOfMeasure
     */
    protected function addTemperatures()
    {
        $fUnit = Temperature::getUnit('F');
        $fUnit->addAlias('f');

        $cUnit = Temperature::getUnit('C');
        $cUnit->addAlias('c');
    }
}
