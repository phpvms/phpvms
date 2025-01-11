<?php

namespace App\Http\Requests;

use App\Contracts\FormRequest;
use App\Models\Fare;

class CreateFareRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = Fare::$rules;
        $rules['code'] .= '|unique:fares';

        return $rules;
    }
}
