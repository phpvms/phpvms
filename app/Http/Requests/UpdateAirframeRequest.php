<?php

namespace App\Http\Requests;

use App\Contracts\FormRequest;
use App\Models\SimBriefAirframe;

class UpdateAirframeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return SimBriefAirframe::$rules;
    }
}
