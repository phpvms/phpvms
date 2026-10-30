<?php

namespace App\Http\Requests\Acars;

use App\Contracts\FormRequest;
use App\Models\Pirep;
use Illuminate\Support\Facades\Auth;

/**
 * Authorizes a body-less PIREP write (cancel, route delete, finance
 * recalculation) for the PIREP's owner only.
 */
class OwnPirepRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pirep = Pirep::findOrFail($this->route('pirep_id'), ['user_id']);

        return $pirep->user_id === Auth::id();
    }
}
