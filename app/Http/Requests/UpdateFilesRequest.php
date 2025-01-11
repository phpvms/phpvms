<?php

namespace App\Http\Requests;

use App\Contracts\FormRequest;

class UpdateFilesRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required',
            'file' => 'nullable|file',
        ];
    }
}
