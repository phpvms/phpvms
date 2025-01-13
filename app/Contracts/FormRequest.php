<?php

namespace App\Contracts;

use Illuminate\Validation\Rule;

class FormRequest extends \Illuminate\Foundation\Http\FormRequest
{
    /**
     * Authorized by default.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }

    /**
     * Set a given column as being unique.
     *
     *
     * @return array
     */
    public function unique($table)
    {
        return [
            Rule::unique($table)->ignore($this->id, 'id'),
        ];
    }
}
