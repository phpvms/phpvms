<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name'            => 'required',
            'email'           => 'required|email|unique:users,email',
            'airline_id'      => 'required',
            'home_airport_id' => 'required',
            'password'        => 'required',
            'timezone'        => 'required',
            'country'         => 'required',
            'transfer_time'   => 'sometimes|integer|min:0',
        ];
    }
}
