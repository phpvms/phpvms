<?php

declare(strict_types=1);

namespace App\Features\Hooks\Points\App\Slots;

use App\Features\Hooks\RenderHook;
use App\Http\Data\BidData;
use App\Http\Data\FlightListItemData;

/**
 * Render hook for the per-row action cell of a bid card on the My Bids page.
 *
 * `forList()` is the form used where no single row exists: `$bid` and
 * `$flight` are null and `isList()` is true.
 */
final class SlotBidRowActions extends RenderHook
{
    public function __construct(
        public readonly ?BidData $bid,
        public readonly ?FlightListItemData $flight,
    ) {
        parent::__construct();
    }

    public static function forList(): self
    {
        return new self(null, null);
    }

    public function isList(): bool
    {
        return !$this->bid instanceof BidData;
    }

    public static function name(): string
    {
        return 'bids.row.actions';
    }
}
