<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Enums\PirepState;
use App\Models\Pirep;
use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class PilotChromeData extends Data
{
    public function __construct(
        public ?ActiveSectorData $activeSector,
        public DutyStateData $duty,
        public ?WeatherStationData $station,
    ) {}

    public static function fromUser(User $user): self
    {
        $user->loadMissing(['current_airport', 'home_airport']);

        $activePirep = Pirep::query()
            ->with('airline')
            ->where('user_id', $user->id)
            ->whereIn('state', [PirepState::IN_PROGRESS, PirepState::PAUSED])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        return new self(
            activeSector: $activePirep ? ActiveSectorData::fromModel($activePirep) : null,
            duty: DutyStateData::fromPirepState($activePirep?->state),
            station: WeatherStationData::fromModel($user->current_airport ?? $user->home_airport),
        );
    }
}
