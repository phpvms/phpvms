<?php

namespace App\Models\Enums;

use App\Contracts\Enum;

/**
 * Tied to the ACARS statuses/states.
 * Corresponds to values from AIDX and ICAO ADREP
 * https://www.skybrary.aero/index.php/Flight_Phase_Taxonomy
 */
class PirepStatus extends Enum
{
    public const INITIATED = 'INI';

    public const SCHEDULED = 'SCH';

    public const BOARDING = 'BST';

    public const RDY_START = 'RDT';

    public const PUSHBACK_TOW = 'PBT';

    public const DEPARTED = 'OFB'; // Off block

    public const RDY_DEICE = 'DIR';

    public const STRT_DEICE = 'DIC';

    public const GRND_RTRN = 'GRT'; // Ground return

    public const TAXI = 'TXI'; // Taxi

    public const TAKEOFF = 'TOF';

    public const INIT_CLIM = 'ICL';

    public const AIRBORNE = 'TKO';

    public const ENROUTE = 'ENR';

    public const DIVERTED = 'DV';

    public const APPROACH = 'TEN';

    public const APPROACH_ICAO = 'APR';

    public const ON_FINAL = 'FIN';

    public const LANDING = 'LDG';

    public const LANDED = 'LAN';

    public const ON_BLOCK = 'ONB';

    public const ARRIVED = 'ARR';

    public const CANCELLED = 'DX';

    public const EMERG_DESCENT = 'EMG';

    public const PAUSED = 'PSD';

    protected static array $labels = [
        self::INITIATED     => 'pireps.status.initialized',
        self::SCHEDULED     => 'pireps.status.scheduled',
        self::BOARDING      => 'pireps.status.boarding',
        self::RDY_START     => 'pireps.status.ready_start',
        self::PUSHBACK_TOW  => 'pireps.status.push_tow',
        self::DEPARTED      => 'pireps.status.departed',
        self::RDY_DEICE     => 'pireps.status.ready_deice',
        self::STRT_DEICE    => 'pireps.status.deicing',
        self::GRND_RTRN     => 'pireps.status.ground_ret',
        self::TAXI          => 'pireps.status.taxi',
        self::TAKEOFF       => 'pireps.status.takeoff',
        self::INIT_CLIM     => 'pireps.status.initial_clb',
        self::AIRBORNE      => 'pireps.status.enroute',
        self::ENROUTE       => 'pireps.status.enroute',
        self::DIVERTED      => 'pireps.status.diverted',
        self::APPROACH      => 'pireps.status.approach',
        self::APPROACH_ICAO => 'pireps.status.approach',
        self::ON_FINAL      => 'pireps.status.final_appr',
        self::LANDING       => 'pireps.status.landing',
        self::LANDED        => 'pireps.status.landed',
        self::ON_BLOCK      => 'pireps.status.arrived',
        self::ARRIVED       => 'pireps.status.arrived',
        self::CANCELLED     => 'pireps.status.cancelled',
        self::EMERG_DESCENT => 'pireps.status.emerg_decent',
        self::PAUSED        => 'pireps.status.paused',
    ];
}
