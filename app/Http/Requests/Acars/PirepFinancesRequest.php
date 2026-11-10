<?php

namespace App\Http\Requests\Acars;

use Illuminate\Support\Facades\Auth;

/**
 * PIREP finances are for the owner or a PIREP admin (admin recalculate button)
 */
class PirepFinancesRequest extends OwnPirepRequest
{
    public function authorize(): bool
    {
        return parent::authorize() || Auth::user()->ability('admin', 'pireps');
    }
}
