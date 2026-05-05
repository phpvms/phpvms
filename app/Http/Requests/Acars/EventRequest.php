<?php

declare(strict_types=1);

namespace App\Http\Requests\Acars;

use App\Contracts\FormRequest;
use App\Models\Pirep;
use Illuminate\Support\Facades\Auth;

class EventRequest extends FormRequest
{
    #[\Override]
    public function authorize(): bool
    {
        $pirep = Pirep::findOrFail($this->route('pirep_id'), ['user_id']);

        return $pirep->user_id === Auth::id();
    }

    #[\Override]
    public function rules(): array
    {
        return [
            'events'              => 'required|array',
            'events.*.event'      => 'required',
            'events.*.lat'        => 'sometimes|numeric',
            'events.*.lon'        => 'sometimes|numeric',
            'events.*.created_at' => 'sometimes|date',
        ];
    }
}
