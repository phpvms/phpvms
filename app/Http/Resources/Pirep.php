<?php

namespace App\Http\Resources;

use App\Contracts\Resource;
use App\Http\Resources\SimBrief as SimbriefResource;
use App\Models\Enums\PirepStatus;
use App\Support\Units\Distance;
use App\Support\Units\Fuel;

/**
 * @mixin \App\Models\Pirep
 */
class Pirep extends Resource
{
    /**
     * Transform the resource into an array.
     *
     * @return array
     */
    public function toArray(\Illuminate\Http\Request $request)
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

        $res['aircraft'] = new Aircraft($this->aircraft);
        $res['airline'] = new Airline($this->airline);
        $res['dpt_airport'] = new Airport($this->dpt_airport);
        $res['arr_airport'] = new Airport($this->arr_airport);

        $res['position'] = Acars::make($this->whenLoaded('position'));
        $res['comments'] = PirepComment::collection($this->whenLoaded('comments'));
        $res['user'] = User::make($this->whenLoaded('user'));

        $res['flight'] = Flight::make($this->whenLoaded('flight'));

        // format to kvp
        $res['fields'] = new PirepFieldCollection($this->fields);

        // Simbrief info
        $res['simbrief'] = new SimbriefResource($this->whenLoaded('simbrief'));

        return $res;
    }
}
