<?php

namespace App\Observers;

use App\Models\SimBrief;
use Illuminate\Support\Facades\Storage;

class SimBriefObserver
{
    public function deleted(SimBrief $simbrief): void
    {
        if ($simbrief->ofp_json_path) {
            Storage::delete($simbrief->ofp_json_path);
        }
    }
}
