<?php

namespace App\Models\Observers;

use App\Models\Subfleet;

/**
 * Class SubfleetObserver
 */
class SubfleetObserver
{
    public function creating(Subfleet $model): void
    {
        if (!filled($model->ground_handling_multiplier)) {
            $model->ground_handling_multiplier = 100;
        }
    }
}
