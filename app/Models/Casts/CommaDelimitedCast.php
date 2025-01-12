<?php

namespace App\Models\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class CommaDelimitedCast implements CastsAttributes
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
        if (empty($value) || empty(trim($value))) {
            return [];
        }

        return explode(',', $value);
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
        if (is_array($value)) {
            return implode(',', $value);
        }

        return trim($value);
    }
}
