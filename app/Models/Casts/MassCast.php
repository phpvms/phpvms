<?php

namespace App\Models\Casts;

use App\Support\Units\Mass;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use PhpUnitsOfMeasure\Exception\NonNumericValue;
use PhpUnitsOfMeasure\Exception\NonStringUnitName;

class MassCast implements CastsAttributes
{
    /**
     * Transform the attribute from the underlying model values.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string                              $key
     * @param mixed                               $value
     * @param array                               $attributes
     *
     * @return mixed
     */
    public function get($model, string $key, $value, array $attributes)
    {
        if ($value instanceof Mass) {
            return $value;
        }

        try {
            return Mass::make($value, config('phpvms.internal_units.mass'));
        } catch (NonNumericValue $e) {
        } catch (NonStringUnitName $e) {
            return $value;
        }

        return $value;
    }

    /**
     * Transform the attribute to its underlying model values.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param string                              $key
     * @param mixed                               $value
     * @param array                               $attributes
     *
     * @return mixed
     */
    public function set($model, string $key, $value, array $attributes)
    {
        if ($value instanceof Mass) {
            return $value->toUnit(config('phpvms.internal_units.mass'));
        }

        return $value;
    }
}
