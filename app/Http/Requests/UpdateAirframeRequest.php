<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Contracts\FormRequest;
use App\Models\SimBriefAirframe;
use Override;

class UpdateAirframeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    #[Override]
    public function rules(): array
    {
        return SimBriefAirframe::$rules;
    }
}
