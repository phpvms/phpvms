<?php

declare(strict_types=1);

namespace App\Http\Requests\Acars;

use App\Contracts\FormRequest;
use App\Models\Pirep;
use Illuminate\Support\Facades\Auth;
use Override;

/**
 * Authorizes a body-less PIREP write (cancel, route delete, finance
 * recalculation) for the PIREP's owner only.
 */
class OwnPirepRequest extends FormRequest
{
    #[Override]
    public function authorize(): bool
    {
        $pirep = Pirep::findOrFail($this->route('pirep_id'), ['user_id']);

        return $pirep->user_id === Auth::id();
    }
}
