<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Enums\PirepState;
use App\Models\Pirep;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class ActiveSectorData extends Data
{
    public function __construct(
        public string $pirepId,
        public string $ident,
        public string $departureIcao,
        public string $arrivalIcao,
        public string $state,
    ) {}

    public static function fromModel(Pirep $pirep): self
    {
        return new self(
            pirepId: $pirep->id,
            ident: $pirep->ident,
            departureIcao: $pirep->dpt_airport_id,
            arrivalIcao: $pirep->arr_airport_id,
            state: match ($pirep->state) {
                PirepState::IN_PROGRESS => 'in_progress',
                PirepState::PAUSED      => 'paused',
            },
        );
    }
}
