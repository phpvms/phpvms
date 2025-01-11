<?php

namespace App\Http\Requests;

use App\Contracts\FormRequest;
use App\Models\Fare;

class UpdateFareRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return Fare::$rules;
    }
}
