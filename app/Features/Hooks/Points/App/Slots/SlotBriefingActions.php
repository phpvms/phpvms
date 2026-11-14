<?php

declare(strict_types=1);

namespace App\Features\Hooks\Points\App\Slots;

use App\Features\Hooks\RenderHook;
use App\Http\Data\BidData;
use App\Http\Data\EligibleAircraftData;
use App\Http\Data\FlightDetailData;
use App\Http\Data\SimBriefBriefingData;

/**
 * Render hook for the actions area of the SimBrief briefing page, above the
 * OFP lifecycle buttons. The values are the DTOs the briefing page holds; `$flight`,
 * `$bid` and `$aircraft` are the briefing's own.
 */
final class SlotBriefingActions extends RenderHook
{
    public function __construct(
        public readonly FlightDetailData $flight,
        public readonly ?BidData $bid,
        public readonly SimBriefBriefingData $briefing,
        public readonly EligibleAircraftData $aircraft,
    ) {
        parent::__construct();
    }

    public static function name(): string
    {
        return 'flight.briefing.actions';
    }
}
