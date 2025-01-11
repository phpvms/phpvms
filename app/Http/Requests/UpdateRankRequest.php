<?php

namespace App\Http\Requests;

use App\Contracts\FormRequest;
use App\Models\Rank;

class UpdateRankRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return Rank::$rules;
    }
}
