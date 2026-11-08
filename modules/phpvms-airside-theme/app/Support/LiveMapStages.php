<?php

declare(strict_types=1);

namespace Modules\PhpvmsAirsideTheme\Support;

use App\Enums\PirepPhase;

/**
 * Groups the PIREP phases into the four stages the live map filters and counts by.
 * The island receives the result keyed by phase value, so the JS never repeats them.
 */
final class LiveMapStages
{
    public const string GROUND = 'ground';

    public const string DEPARTING = 'departing';

    public const string ENROUTE = 'enroute';

    public const string ARRIVING = 'arriving';

    /**
     * The stage of one phase. Cancelled has no stage: it shows under "All" only.
     */
    public static function of(PirepPhase $phase): ?string
    {
        return match ($phase) {
            PirepPhase::INITIATED,
            PirepPhase::SCHEDULED,
            PirepPhase::BOARDING,
            PirepPhase::RDY_START,
            PirepPhase::PUSHBACK_TOW,
            PirepPhase::DEPARTED,
            PirepPhase::RDY_DEICE,
            PirepPhase::STRT_DEICE,
            PirepPhase::GRND_RTRN,
            PirepPhase::TAXI,
            PirepPhase::LANDED,
            PirepPhase::ON_BLOCK,
            PirepPhase::ARRIVED => self::GROUND,
            PirepPhase::TAKEOFF,
            PirepPhase::INIT_CLIM,
            PirepPhase::AIRBORNE => self::DEPARTING,
            PirepPhase::ENROUTE,
            PirepPhase::DIVERTED,
            PirepPhase::PAUSED => self::ENROUTE,
            PirepPhase::APPROACH,
            PirepPhase::APPROACH_ICAO,
            PirepPhase::ON_FINAL,
            PirepPhase::LANDING,
            PirepPhase::EMERG_DESCENT => self::ARRIVING,
            PirepPhase::CANCELLED     => null,
        };
    }

    /**
     * Every phase value with a stage, for the island config.
     *
     * @return array<string, string>
     */
    public static function map(): array
    {
        $stages = [];

        foreach (PirepPhase::cases() as $phase) {
            $stage = self::of($phase);

            if ($stage !== null) {
                $stages[$phase->value] = $stage;
            }
        }

        return $stages;
    }
}
