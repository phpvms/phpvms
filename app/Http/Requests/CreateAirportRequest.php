<?php

namespace App\Http\Requests;

use App\Contracts\FormRequest;
use App\Models\Airport;

class CreateAirportRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = Airport::$rules;
        $rules['icao'] .= '|unique:airports';

        return $rules;
    }
}
