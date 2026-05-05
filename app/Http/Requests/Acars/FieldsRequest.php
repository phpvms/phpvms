<?php

declare(strict_types=1);

namespace App\Http\Requests\Acars;

use App\Contracts\FormRequest;

class FieldsRequest extends FormRequest
{
    #[\Override]
    public function rules(): array
    {
        return [
            'fields' => 'required|array',
        ];
    }
}
