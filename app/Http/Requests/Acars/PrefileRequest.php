<?php

declare(strict_types=1);

namespace App\Http\Requests\Acars;

use App\Contracts\FormRequest;
use App\Enums\SimType;
use Illuminate\Validation\Rules\Enum;
use Override;

class PrefileRequest extends FormRequest
{
    #[Override]
    public function rules(): array
    {
        return [
            'airline_id'          => 'required|exists:airlines,id',
            'aircraft_id'         => 'required|exists:aircraft,id',
            'flight_id'           => 'sometimes|nullable|exists:flights,id',
            'flight_number'       => 'required',
            'dpt_airport_id'      => 'required',
            'arr_airport_id'      => 'required',
            'source_name'         => 'required',
            'sim_type'            => ['sometimes', 'nullable', new Enum(SimType::class)],
            'alt_airport_id'      => 'sometimes',
            'status'              => 'sometimes',
            'level'               => 'nullable|numeric',
            'flight_type'         => 'sometimes',
            'route_code'          => 'sometimes',
            'route_leg'           => 'sometimes',
            'distance'            => 'sometimes|numeric',
            'block_time'          => 'sometimes|integer',
            'flight_time'         => 'sometimes|integer',
            'planned_distance'    => 'sometimes|numeric',
            'planned_flight_time' => 'sometimes|integer',
            'zfw'                 => 'sometimes|numeric',
            'block_fuel'          => 'sometimes|numeric',
            'route'               => 'nullable',
            'notes'               => 'nullable',
            'score'               => 'sometimes|integer',
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
