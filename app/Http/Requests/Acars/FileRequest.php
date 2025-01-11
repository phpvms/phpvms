<?php

namespace App\Http\Requests\Acars;

use App\Contracts\FormRequest;
use App\Models\Pirep;
use Illuminate\Support\Facades\Auth;

class FileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pirep = Pirep::findOrFail($this->route('pirep_id'), ['user_id']);

        return $pirep->user_id === Auth::id();
    }

    public function rules(): array
    {
        return [
            'distance'            => 'required|numeric',
            'flight_time'         => 'required|integer',
            'fuel_used'           => 'sometimes|numeric',
            'block_time'          => 'sometimes|integer',
            'airline_id'          => 'sometimes|exists:airlines,id',
            'aircraft_id'         => 'sometimes|exists:aircraft,id',
            'flight_number'       => 'sometimes',
            'flight_type'         => 'sometimes',
            'dpt_airport_id'      => 'sometimes',
            'arr_airport_id'      => 'sometimes',
            'route_code'          => 'sometimes',
            'route_leg'           => 'sometimes',
            'planned_distance'    => 'sometimes|numeric',
            'planned_flight_time' => 'sometimes|integer',
            'level'               => 'sometimes|numeric',
            'zfw'                 => 'sometimes|numeric',
            'block_fuel'          => 'sometimes|numeric',
            'route'               => 'sometimes',
            'notes'               => 'sometimes',
            'source_name'         => 'sometimes',
            'score'               => 'sometimes|integer',
            'landing_rate'        => 'sometimes|numeric',
            'block_off_time'      => 'sometimes|date',
            'block_on_time'       => 'sometimes|date',
            'created_at'          => 'sometimes|date',

            // See if the fare objects are included and formatted properly
            'fares'         => 'nullable|array',
            'fares.*.id'    => 'required',
            'fares.*.count' => 'required|numeric',
        ];
    }
}
