<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Features\Tour\Enums\TourStatus;
use App\Features\Tour\Models\UserTour;
use App\Models\Flight;
use App\Models\FlightBundle;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One tour card on the pilot-facing Tours page: the bundle's identity and
 * schedule window, its legs in order, and the pilot's latest run through it.
 *
 * `status` / `legsCompleted` come from the pilot's most recent user_tours row
 * for the bundle (null when they have never bid it). `activeLegFlightId` is
 * the flight the page's action button opens: the current leg of a run in
 * progress, otherwise leg 1 of a valid sequence.
 */
#[TypeScript]
final class TourListItemData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public ?string $image,
        public ?string $startDate,
        public ?string $endDate,
        public bool $valid,
        /** @var TourLegData[] */
        public array $legs,
        public ?string $status,
        public int $legsCompleted,
        public ?string $activeLegFlightId,
    ) {}

    public static function fromModel(FlightBundle $bundle, ?UserTour $userTour): self
    {
        $sequence = $bundle->tourLegSequence();
        $flights = $sequence['flights'];
        $flights->loadMissing('airline');

        $flownFlightIds = [];
        foreach ($userTour->legs ?? [] as $leg) {
            if (!empty($leg['filed_at'])) {
                $flownFlightIds[$leg['flight_id']] = true;
            }
        }

        $inProgress = $userTour?->status === TourStatus::InProgress;

        return new self(
            id: $bundle->id,
            name: $bundle->name,
            description: $bundle->description,
            image: $bundle->image_url,
            startDate: $bundle->start_date?->toDateString(),
            endDate: $bundle->end_date?->toDateString(),
            valid: $sequence['valid'],
            legs: $flights
                ->map(fn (Flight $flight): TourLegData => TourLegData::fromModel($flight, $flownFlightIds))
                ->values()
                ->all(),
            status: $userTour?->status->value,
            legsCompleted: $userTour->legs_completed ?? 0,
            activeLegFlightId: $inProgress
                ? $userTour->flight_id
                : ($sequence['valid'] ? $flights->first()?->id : null),
        );
    }
}
