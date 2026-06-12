<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Contracts\Resource;
use App\Models\Subfleet;
use App\Services\FareService;
use Illuminate\Http\Request;
use Override;

/**
 * @mixin Subfleet
 */
class SubfleetResource extends Resource
{
    #[Override]
    public function toArray(Request $request)
    {
        $res = parent::toArray($request);

        // Apply subfleet-level fare overrides at the response boundary.
        // The access-control loader no longer bakes these in (the financial
        // path through FareService::saveToPirep recomputes server-side at
        // file time and is independent of the loader).
        $fares = app(FareService::class)->getForSubfleet($this->resource);
        $res['fares'] = FareResource::collection($fares);

        $res['aircraft'] = AircraftResource::collection($this->aircraft);

        return $res;
    }
}
