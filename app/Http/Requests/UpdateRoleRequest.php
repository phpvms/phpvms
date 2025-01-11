<?php

namespace App\Http\Requests;

use App\Contracts\FormRequest;
use App\Models\Role;

/**
 * @property array permissions
 */
class UpdateRoleRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return Role::$rules;
    }
}
