<?php

namespace App\Http\Requests\Acars;

use App\Contracts\FormRequest;
use App\Models\Pirep;
use Illuminate\Support\Facades\Auth;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pirep = Pirep::findOrFail($this->route('pirep_id'), ['user_id']);

        return $pirep->user_id === Auth::id();
    }

    public function rules(): array
    {
        return [
            'airline_id'          => 'nullable|exists:airlines,id',
            'aircraft_id'         => 'nullable|exists:aircraft,id',
            'flight_id'           => 'sometimes|nullable|exists:flights,id',
            'flight_number'       => 'sometimes|required',
            'dpt_airport_id'      => 'sometimes|required',
            'arr_airport_id'      => 'sometimes|required',
            'route_code'          => 'nullable',
            'route_leg'           => 'nullable',
            'distance'            => 'sometimes|numeric',
            'planned_distance'    => 'sometimes|numeric',
            'block_time'          => 'sometimes|integer',
            'flight_time'         => 'sometimes|integer',
            'flight_type'         => 'nullable',
            'planned_flight_time' => 'sometimes|integer',
            'level'               => 'sometimes|numeric',
            'zfw'                 => 'sometimes|numeric',
            'fuel_used'           => 'sometimes|numeric',
            'block_fuel'          => 'sometimes|numeric',
            'route'               => 'sometimes|nullable',
            'notes'               => 'sometimes|nullable',
            'source_name'         => 'sometimes|max:25',
            'landing_rate'        => 'sometimes|numeric',
            'block_off_time'      => 'sometimes|date',
            'block_on_time'       => 'sometimes|date',
            'created_at'          => 'sometimes|date',
            'status'              => 'sometimes',
            'score'               => 'nullable|integer',

            // See if the fare objects are included and formatted properly
            'fares'         => 'nullable|array',
            'fares.*.id'    => 'required',
            'fares.*.count' => 'required|numeric',
        ];
    }
}
