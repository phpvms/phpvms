<?php

namespace App\Http\Resources;

use App\Contracts\Resource;

class JournalTransactionResource extends Resource
{
    public function toArray($request)
    {
        $transaction = parent::toArray($request);

        return $transaction;
    }
}
