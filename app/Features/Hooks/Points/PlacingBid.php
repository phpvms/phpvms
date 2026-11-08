<?php

declare(strict_types=1);

namespace App\Features\Hooks\Points;

use App\Features\Hooks\GateHook;
use App\Models\Aircraft;
use App\Models\Flight;
use App\Models\User;

/**
 * Gate consulted before any bid is created, including the first leg of a tour.
 *
 * Runs before core's own checks and outside their row locks, so addon code
 * never holds them. An addon can only add a reason to refuse; core's rules
 * (bid limit, conflicts, aircraft eligibility) still apply afterwards.
 */
final class PlacingBid extends GateHook
{
    public function __construct(
        public readonly User $user,
        public readonly Flight $flight,
        public readonly ?Aircraft $aircraft,
        public readonly bool $startsTour,
    ) {}

    public static function name(): string
    {
        return 'bid.placing';
    }
}
