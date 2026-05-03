<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasSlug
{
    /**
     * Boot the trait.
     * Laravel automatically calls methods starting with "boot" + TraitName.
     */
    protected static function bootHasSlug(): void
    {
        static::saving(function ($model) {
            if (empty($model->slug) || $model->isDirty('name')) {
                $model->slug = Str::slug($model->name);
            }
        });
    }
}
