<?php

declare(strict_types=1);

namespace App\Features\Hooks\Points\App\Slots;

use App\Features\Hooks\RenderHook;
use App\Http\Data\FlightDispatchPolicyData;
use App\Http\Data\FlightListItemData;

/**
 * Render hook for the per-row action cell of the flight manifest list.
 *
 * `$flight` is the summary DTO for that row. `forList()` is the form used where
 * no single row exists: `$flight` is null and `isList()` is true.
 */
final class SlotFlightRowActions extends RenderHook
{
    public function __construct(
        public readonly ?FlightListItemData $flight,
        public readonly FlightDispatchPolicyData $policy,
    ) {
        parent::__construct();
    }

    public static function forList(FlightDispatchPolicyData $policy): self
    {
        return new self(null, $policy);
    }

    public function isList(): bool
    {
        return !$this->flight instanceof FlightListItemData;
    }

    public static function name(): string
    {
        return 'flights.row.actions';
    }
}
