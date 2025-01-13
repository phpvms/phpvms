<?php

namespace App\Models\Observers;

/**
 * Create a slug from a name.
 *
 * @property object attributes
 */
class Sluggable
{
    public function creating($model): void
    {
        $model->slug = str_slug($model->name);
    }

    public function updating($model): void
    {
        $model->slug = str_slug($model->name);
    }

    public function setNameAttribute($name): void
    {
        $this->attributes['name'] = $name;
        $this->attributes['slug'] = str_slug($name);
    }
}
