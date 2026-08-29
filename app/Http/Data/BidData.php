<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Models\Bid;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Flat SPA projection of a saved bid (the identifiers the SPA row needs).
 * SPA-only; the Blade path still receives the Eloquent Bid model.
 */
#[TypeScript]
final class BidData extends Data
{
    public function __construct(
        // Bid PK is an auto-increment int (unlike flight_id, a nanoid string).
        public int $id,
        public string $flightId,
        public ?int $aircraftId,
        // Set when this bid is one leg of a tour run; removing it cancels
        // the whole run (FlightController::destroyBid).
        public ?string $userTourId,
    ) {}

    public static function fromModel(Bid $bid): self
    {
        return new self(
            id: $bid->id,
            flightId: $bid->flight_id,
            aircraftId: $bid->aircraft_id,
            userTourId: $bid->user_tour_id,
        );
    }
}
