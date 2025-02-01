<?php

namespace App\Models\Observers;

/**
 * Create a slug from a name
 *
 * @property object attributes
 */
class Sluggable
{
    /**
     * @var array<string, mixed>
     */
    public $attributes;
    public function creating($model): void
    {
        $model->slug = \Illuminate\Support\Str::slug($model->name);
    }

    public function updating($model): void
    {
        $model->slug = \Illuminate\Support\Str::slug($model->name);
    }

    public function setNameAttribute($name): void
    {
        $this->attributes['name'] = $name;
        $this->attributes['slug'] = \Illuminate\Support\Str::slug($name);
    }
}
