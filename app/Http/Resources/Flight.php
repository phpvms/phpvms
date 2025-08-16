<?php

namespace App\Http\Resources;

use App\Contracts\Resource;
use App\Http\Resources\SimBrief as SimbriefResource;
use App\Support\Units\Distance;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use PhpUnitsOfMeasure\Exception\NonNumericValue;
use PhpUnitsOfMeasure\Exception\NonStringUnitName;
use stdClass;

/**
 * @mixin \App\Models\Flight
 */
class Flight extends Resource
{
    /**
     * Set the fields on the flight object
     */
    protected function setFields()
    {
        /** @var Collection $field_values */
        $return_values = new stdClass();
        $field_values = $this->field_values;
        if ($field_values->isEmpty() || $field_values->count() === 0) {
            return $return_values;
        }

        foreach ($field_values as $field) {
            $return_values->{$field->name} = $field->value;
        }

        return $return_values;
    }

    /**
     * @param  Request $request
     * @return array
     *
     * @throws NonNumericValue
     * @throws NonStringUnitName
     */
    public function toArray($request)
    {
        $res = parent::toArray($request);

        // Display flight callsign if pilot ident usage is not forced by VA
        // Check if there is a callsign too
        if (!empty($this->callsign) && !setting('simbrief.callsign', true)) {
            $res['ident'] = $this->atc.' | '.$this->ident;
        } else {
            $res['ident'] = $this->ident;
        }

        if (empty($res['load_factor'])) {
            $res['load_factor'] = setting('flights.default_load_factor');
        }

        if (empty($res['load_factor_variance'])) {
            $res['load_factor_variance'] = setting('flights.load_factor_variance');
        }

        $distance = Distance::make($res['distance'], config('phpvms.internal_units.distance'));
        $res['distance'] = $distance->getResponseUnits();

        $res['airline'] = new Airline($this->airline);
        $res['subfleets'] = Subfleet::collection($this->whenLoaded('subfleets'));
        $res['fields'] = $this->setFields();

        // Simbrief info
        $res['simbrief'] = new SimbriefResource($this->whenLoaded('simbrief'));

        return $res;
    }
}
