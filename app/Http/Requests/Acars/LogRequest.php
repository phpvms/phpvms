<?php

declare(strict_types=1);

namespace App\Http\Requests\Acars;

use App\Contracts\FormRequest;
use App\Models\Pirep;
use Illuminate\Support\Facades\Auth;
use Override;

class LogRequest extends FormRequest
{
    #[Override]
    public function authorize(): bool
    {
        $pirep = Pirep::findOrFail($this->route('pirep_id'), ['user_id']);

        return $pirep->user_id === Auth::id();
    }

    #[Override]
    public function rules(): array
    {
        return [
            'logs'              => 'required|array',
            'logs.*.log'        => 'required|string|max:1000',
            'logs.*.lat'        => 'sometimes|numeric',
            'logs.*.lon'        => 'sometimes|numeric',
            'logs.*.created_at' => 'sometimes|date',
        ];
    }
}
