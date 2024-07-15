<?php

namespace App\Http\Requests\Acars;

use App\Contracts\FormRequest;
use App\Models\Pirep;
use Illuminate\Support\Facades\Auth;

class PositionRequest extends FormRequest
{
    /**
     * Is the user allowed to do this?
     */
    public function authorize(): bool
    {
        $pirep = Pirep::findOrFail($this->route('pirep_id'), ['user_id']);

        return $pirep->user_id === Auth::id();
    }

    public function rules(): array
    {
        return [
            'positions'                => 'required|array',
            'positions.*.lat'          => 'required|numeric',
            'positions.*.lon'          => 'required|numeric',
            'positions.*.status'       => 'sometimes',
            'positions.*.altitude'     => 'sometimes|numeric',
            'positions.*.altitude_agl' => 'sometimes|numeric',
            'positions.*.altitude_msl' => 'sometimes|numeric',
            'positions.*.heading'      => 'sometimes|numeric|between:0,360',
            'positions.*.vs'           => 'sometimes',
            'positions.*.gs'           => 'sometimes',
            'positions.*.transponder'  => 'sometimes',
            'positions.*.autopilot'    => 'sometimes',
            'positions.*.fuel'         => 'sometimes|numeric',
            'positions.*.fuel_flow'    => 'sometimes|numeric',
            'positions.*.log'          => 'sometimes|nullable',
            'positions.*.sim_time'     => 'sometimes|date',
            'positions.*.created_at'   => 'sometimes|date',
        ];
    }
}
