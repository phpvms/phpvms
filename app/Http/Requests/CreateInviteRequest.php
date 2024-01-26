<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateInviteRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'email'       => 'nullable|string',
            'usage_limit' => 'nullable|integer',
            'expires_at'  => 'nullable|date|after:today',
        ];
    }
}
