<?php

namespace App\Models\Observers;

use Illuminate\Support\Str;

/**
 * Create a slug from a name
 */
class Sluggable
{
    /**
     * @var array<string, mixed>
     */
    public $attributes;

    public function creating($model): void
    {
        $model->slug = Str::slug($model->name);
    }

    public function updating($model): void
    {
        $model->slug = Str::slug($model->name);
    }

    public function setNameAttribute($name): void
    {
        $this->attributes['name'] = $name;
        $this->attributes['slug'] = Str::slug($name);
    }
}
