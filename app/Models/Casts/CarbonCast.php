<?php

namespace App\Models\Casts;

use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Cast into a Carbon DateTime instance
 */
class CarbonCast implements CastsAttributes
{
    /**
     * Transform the attribute from the underlying model values.
     *
     * @param  Model $model
     * @param  mixed $value
     * @return mixed
     */
    public function get($model, string $key, $value, array $attributes)
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        return new Carbon($value);
    }

    /**
     * Transform the attribute to its underlying model values.
     *
     * @param  Model $model
     * @param  mixed $value
     * @return mixed
     */
    public function set($model, string $key, $value, array $attributes)
    {
        if ($value instanceof Carbon) {
            return $value->toIso8601ZuluString();
        }

        return $value;
    }
}
