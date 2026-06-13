<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Contracts\Resource;
use Illuminate\Http\Request;
use Override;

class JournalTransactionResource extends Resource
{
    #[Override]
    public function toArray(Request $request)
    {
        return parent::toArray($request);
    }
}
