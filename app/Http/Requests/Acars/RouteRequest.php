<?php

namespace App\Http\Requests\Acars;

use App\Contracts\FormRequest;
use App\Models\Pirep;
use Illuminate\Support\Facades\Auth;

class RouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pirep = Pirep::findOrFail($this->route('pirep_id'), ['user_id']);

        return $pirep->user_id === Auth::id();
    }

    public function rules(): array
    {
        return [
            'route'            => 'required|array',
            'route.*.name'     => 'required',
            'route.*.order'    => 'required|int',
            'route.*.nav_type' => 'sometimes|int',
            'route.*.lat'      => 'required|numeric',
            'route.*.lon'      => 'required|numeric',
        ];
    }
}
