<?php

namespace App\Http\Requests\Acars;

use App\Contracts\FormRequest;
use App\Models\Pirep;
use Illuminate\Support\Facades\Auth;

class CommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pirep = Pirep::findOrFail($this->route('pirep_id'), ['user_id']);

        return $pirep->user_id === Auth::id();
    }

    public function rules(): array
    {
        return [
            'comment'    => 'required',
            'created_at' => 'sometimes|date',
        ];
    }
}
