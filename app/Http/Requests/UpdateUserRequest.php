<?php

namespace App\Http\Requests;

use App\Contracts\FormRequest;
use App\Models\User;

use function request;

class UpdateUserRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = User::$rules;

        $user_id = request('id', null);

        $rules['email'] .= '|unique:users,email,'.$user_id.',id';
        $rules['pilot_id'] .= '|unique:users,pilot_id,'.$user_id.',id';

        return $rules;
    }
}
