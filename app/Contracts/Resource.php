<?php

namespace App\Contracts;

use App\Support\Resources\CustomAnonymousResourceCollection;
use App\Support\Resources\CustomPaginatedResourceResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\AbstractPaginator;

/**
 * Base class for a resource/response
 */
class Resource extends JsonResource
{
    /**
     * Iterate through the list of $fields and check if they're a "Unit"
     * If they are, then add the response
     */
    public function checkUnitFields(&$response, array $fields): void
    {
        foreach ($fields as $f) {
            $response[$f] = $this->{$f} instanceof Unit ? $this->{$f}->getResponseUnits() : $this->{$f};
        }
    }

    /**
     * Customize the response to exclude all the extra data that isn't used. Based on:
     * https://gist.github.com/derekphilipau/4be52164a69ce487dcd0673656d280da
     *
     * @param  Request      $request
     * @return JsonResponse
     */
    public function toResponse($request)
    {
        return $this->resource instanceof AbstractPaginator
                    ? (new CustomPaginatedResourceResponse($this))->toResponse($request)
                    : parent::toResponse($request);
    }

    public static function collection($resource)
    {
        return tap(new CustomAnonymousResourceCollection($resource, static::class), function ($collection) {
            if (property_exists(static::class, 'preserveKeys')) {
                $collection->preserveKeys = (new static([]))->preserveKeys === true;
            }
        });
    }
}
