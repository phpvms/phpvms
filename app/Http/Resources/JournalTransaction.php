<?php

namespace App\Http\Resources;

use App\Contracts\Resource;

class JournalTransaction extends Resource
{
    public function toArray(\Illuminate\Http\Request $request)
    {
        return parent::toArray($request);
    }
}
