<?php

namespace App\Http\Requests;

use App\Contracts\FormRequest;
use App\Models\Aircraft;

class UpdateAircraftRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return Aircraft::$rules;
    }
}
