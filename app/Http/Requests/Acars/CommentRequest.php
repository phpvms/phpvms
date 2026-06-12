<?php

declare(strict_types=1);

namespace App\Http\Requests\Acars;

use App\Contracts\FormRequest;
use Override;

class CommentRequest extends FormRequest
{
    #[Override]
    public function rules(): array
    {
        return [
            'comment'    => 'required',
            'created_at' => 'sometimes|date',
        ];
    }
}
