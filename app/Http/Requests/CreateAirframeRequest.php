<?php

namespace App\Http\Requests;

use App\Contracts\FormRequest;
use App\Models\SimBriefAirframe;

class CreateAirframeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return SimBriefAirframe::$rules;
    }
}
