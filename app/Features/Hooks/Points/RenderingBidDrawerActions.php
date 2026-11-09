<?php

declare(strict_types=1);

namespace App\Features\Hooks\Points;

use App\Features\Hooks\RenderHook;
use App\Http\Data\BidData;
use App\Http\Data\BidSelectionData;

/**
 * Render hook for the My Bids drawer, overview tab, next to the OFP action.
 *
 * `$selection` is the selected bid row. `forList()` is the form used where no
 * single bid exists: `$bid` and `$selection` are null and `isList()` is true.
 */
final class RenderingBidDrawerActions extends RenderHook
{
    public function __construct(
        public readonly ?BidData $bid,
        public readonly ?BidSelectionData $selection,
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
        return 'bids.drawer.overview.actions';
    }
}
