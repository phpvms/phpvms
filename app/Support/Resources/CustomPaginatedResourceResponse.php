<?php

namespace App\Support\Resources;

use Illuminate\Http\Resources\Json\PaginatedResourceResponse;
use Illuminate\Support\Arr;

class CustomPaginatedResourceResponse extends PaginatedResourceResponse
{
    #[\Override]
    protected function paginationLinks($paginated)
    {
        return [
            'first' => null,
            'last'  => null,
            'prev'  => null,
            'next'  => null,
        ];
    }

    #[\Override]
    protected function meta($paginated)
    {
        $arr = Arr::except($paginated, [
            'data',
            'first_page_url',
            'last_page_url',
            'prev_page_url',
            'next_page_url',
            'links',
        ]);

        $arr['prev_page'] = $paginated['prev_page_url'];
        $arr['next_page'] = $paginated['next_page_url'];

        return $arr;
    }
}
