<?php

namespace App\Http\Requests;

use App\Contracts\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePageRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                Rule::unique('pages')->ignore($this->id, 'id'),
            ],
            'body' => 'nullable',
        ];
    }
}
