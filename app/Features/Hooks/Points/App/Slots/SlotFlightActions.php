<?php

declare(strict_types=1);

namespace App\Features\Hooks\Points\App\Slots;

use App\Features\Hooks\RenderHook;
use App\Http\Data\FlightDispatchPolicyData;
use App\Http\Data\FlightListItemData;
use App\Models\Bid;
use App\Models\SimBrief;
use App\Models\User;

/**
 * Render hook for the action area of the flight detail panel and the flight
 * manifest aside.
 *
 * `$flight` is the summary DTO, not the Flight model; a listener that needs the
 * model loads it by `$flight->id`. Under the SPA, a fill's props use the
 * `'@flight'` and `'@bid'` refs. `$policy` is the availability policy.
 *
 * `forList()` is the form used where no single flight exists yet: `$flight` is
 * null and `isList()` is true.
 */
final class SlotFlightActions extends RenderHook
{
    public function __construct(
        public readonly ?FlightListItemData $flight,
        public readonly FlightDispatchPolicyData $policy,
        public readonly User $user,
        public readonly ?Bid $bid,
        public readonly ?SimBrief $briefing,
    ) {
        parent::__construct();
    }

    public static function forList(FlightDispatchPolicyData $policy, User $user): self
    {
        return new self(null, $policy, $user, null, null);
    }

    public function isList(): bool
    {
        return !$this->flight instanceof FlightListItemData;
    }

    public static function name(): string
    {
        return 'flights.detail.actions';
    }
}
