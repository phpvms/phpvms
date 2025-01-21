<?php

namespace App\Models\Traits;

use App\Models\File;

trait FilesTrait
{
    /**
     * Morph to type of File
     *
     * @return mixed
     */
    public function files()
    {
        return $this->morphMany(
            File::class,
            'files',  // overridden by the next two anyway
            'ref_model',
            'ref_model_id'
        );
    }
}
