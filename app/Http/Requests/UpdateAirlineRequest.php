<?php

namespace App\Http\Requests;

use App\Contracts\FormRequest;
use App\Models\Airline;

class UpdateAirlineRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = Airline::$rules;
        $rules['iata'] .= '|unique:airlines,iata,'.$this->id.',id';
        $rules['icao'] .= '|unique:airlines,icao,'.$this->id.',id';

        return Airline::$rules;
    }
}
