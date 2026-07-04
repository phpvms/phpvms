<?php

declare(strict_types=1);

namespace App\Http\Data;

use App\Models\Bid;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One My Bids table row for the SPA: a bid + its flight summary.
 *
 * Shape matches the per-row `context` the page's `bids.row.actions` slot draws
 * (`{ bid, flight }`), so addon slot components keep resolving `@bid` / `@flight`.
 * `flight` is nullable defensively (the controller prunes orphan bids).
 */
#[TypeScript]
final class BidRowData extends Data
{
    public function __construct(
        public BidData $bid,
        public ?FlightData $flight,
    ) {}

    public static function fromModel(Bid $bid): self
    {
        return new self(
            bid: BidData::fromModel($bid),
            flight: $bid->flight ? FlightData::fromModel($bid->flight) : null,
        );
    }
}
