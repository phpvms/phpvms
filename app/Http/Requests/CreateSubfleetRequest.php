<?php

namespace App\Http\Requests;

use App\Contracts\FormRequest;
use App\Models\Subfleet;
use Illuminate\Validation\Rule;

class CreateSubfleetRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = Subfleet::$rules;

        $rules['type'] = explode('|', $rules['type']);
        $rules['type'][] = Rule::unique('subfleets')->whereNull('deleted_at');

        return $rules;
    }
}
