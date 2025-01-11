<?php

namespace App\Http\Requests;

use App\Contracts\FormRequest;
use App\Models\Flight;

class UpdateFlightRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return Flight::$rules;
    }
}
