<?php

namespace App\Http\Resources;

use App\Contracts\Resource;
use App\Http\Resources\SimBriefResource as SimbriefResource;
use App\Models\Enums\PirepStatus;
use App\Models\Pirep;
use App\Support\Units\Distance;
use App\Support\Units\Fuel;
use Illuminate\Http\Request;

/**
 * @mixin Pirep
 */
class PirepResource extends Resource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request $request
     * @return array
     */
    public function toArray($request)
    {
        $res = parent::toArray($request);
        $res['ident'] = $this->ident;
        $res['phase'] = $this->status;
        $res['status_text'] = PirepStatus::label($this->status);

        // Set these to the response units
        if (!array_key_exists('distance', $res)) {
            $res['distance'] = 0;
        }

        $distance = Distance::make($res['distance'], config('phpvms.internal_units.distance'));
        $res['distance'] = $distance->getResponseUnits();

        if (!array_key_exists('block_fuel', $res)) {
            $res['block_fuel'] = 0;
        }

        $block_fuel = Fuel::make($res['block_fuel'], config('phpvms.internal_units.fuel'));
        $res['block_fuel'] = $block_fuel->getResponseUnits();

        if (!array_key_exists('fuel_used', $res)) {
            $res['fuel_used'] = 0;
        }

        $fuel_used = Fuel::make($res['fuel_used'], config('phpvms.internal_units.fuel'));
        $res['fuel_used'] = $fuel_used->getResponseUnits();

        if (!array_key_exists('planned_distance', $res)) {
            $res['planned_distance'] = 0;
        }

        $planned_dist = Distance::make($res['planned_distance'], config('phpvms.internal_units.distance'));
        $res['planned_distance'] = $planned_dist->getResponseUnits();

        /*
         * Relationship fields
         */

        if ($this->block_on_time) {
            $res['block_on_time'] = $this->block_on_time->toIso8601ZuluString();
        }

        if ($this->block_off_time) {
            $res['block_off_time'] = $this->block_off_time->toIso8601ZuluString();
        }

        $res['aircraft'] = new AircraftResource($this->aircraft);
        $res['airline'] = new AirlineResource($this->airline);
        $res['dpt_airport'] = new AirportResource($this->dpt_airport);
        $res['arr_airport'] = new AirportResource($this->arr_airport);

        $res['position'] = AcarsResource::make($this->whenLoaded('position'));
        $res['comments'] = PirepCommentResource::collection($this->whenLoaded('comments'));
        $res['user'] = UserResource::make($this->whenLoaded('user'));

        $res['flight'] = FlightResource::make($this->whenLoaded('flight'));

        // format to kvp
        $res['fields'] = new PirepFieldCollectionResource($this->fields);

        // Simbrief info
        $res['simbrief'] = new SimbriefResource($this->whenLoaded('simbrief'));

        return $res;
    }
}
