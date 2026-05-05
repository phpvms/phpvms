<?php

namespace App\Support\Resources;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\AbstractPaginator;

class CustomAnonymousResourceCollection extends AnonymousResourceCollection
{
    #[\Override]
    public function toResponse($request)
    {
        return $this->resource instanceof AbstractPaginator
                    ? (new CustomPaginatedResourceResponse($this))->toResponse($request)
                    : parent::toResponse($request);
    }
}
