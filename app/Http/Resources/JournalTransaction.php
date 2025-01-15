<?php

namespace App\Http\Resources;

use App\Contracts\Resource;

class JournalTransaction extends Resource
{
    public function toArray($request)
    {
        $transaction = parent::toArray($request);

        return $transaction;
    }
}
