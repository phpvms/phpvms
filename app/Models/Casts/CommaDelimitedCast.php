<?php

namespace App\Models\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class CommaDelimitedCast implements CastsAttributes
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
        if (empty($value) || in_array(trim($value), ['', '0'], true)) {
            return [];
        }

        return explode(',', $value);
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
        if (is_array($value)) {
            return implode(',', $value);
        }

        return trim($value);
    }
}
