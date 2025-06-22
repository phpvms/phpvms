<?php

namespace App\Models\Casts;

use App\Support\Units\Distance;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use PhpUnitsOfMeasure\Exception\NonNumericValue;
use PhpUnitsOfMeasure\Exception\NonStringUnitName;

class DistanceCast implements CastsAttributes
{
    /**
     * Transform the attribute from the underlying model values.
     *
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @param  mixed                               $value
     * @return mixed
     */
    public function get($model, string $key, $value, array $attributes)
    {
        if ($value instanceof Distance) {
            return $value;
        }

        try {
            return new Distance($value, config('phpvms.internal_units.distance'));
        } catch (NonNumericValue) {
        } catch (NonStringUnitName) {
            return $value;
        }

        return $value;
    }

    /**
     * Transform the attribute to its underlying model values.
     *
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @param  mixed                               $value
     * @return mixed
     */
    public function set($model, string $key, $value, array $attributes)
    {
        if ($value instanceof Distance) {
            return $value->toUnit(config('phpvms.internal_units.distance'));
        }

        return $value;
    }
}
