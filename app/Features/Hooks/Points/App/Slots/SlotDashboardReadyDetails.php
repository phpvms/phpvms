<?php

declare(strict_types=1);

namespace App\Features\Hooks\Points\App\Slots;

use App\Features\Hooks\RenderHook;
use App\Models\Airport;
use App\Models\Pirep;
use App\Models\User;

/**
 * Render hook for the details area of the dashboard's ready-to-fly card
 * (airside theme only). `$airport` is the pilot's current or home airport, null
 * when neither is set; `$pirep` is the pilot's last PIREP, if any.
 */
final class SlotDashboardReadyDetails extends RenderHook
{
    public function __construct(
        public readonly ?Airport $airport,
        public readonly User $user,
        public readonly ?Pirep $pirep,
    ) {
        parent::__construct();
    }

    public static function name(): string
    {
        return 'dashboard.ready.details';
    }
}
