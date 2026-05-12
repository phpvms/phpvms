<?php

namespace App\Enums;

use App\Enums\Concerns\HasSelect;
use Filament\Support\Contracts\HasLabel;

enum PirepStatus: string implements HasLabel
{
    use HasSelect;

    case INITIATED = 'INI';
    case SCHEDULED = 'SCH';
    case BOARDING = 'BST';
    case RDY_START = 'RDT';
    case PUSHBACK_TOW = 'PBT';
    case DEPARTED = 'OFB';
    case RDY_DEICE = 'DIR';
    case STRT_DEICE = 'DIC';
    case GRND_RTRN = 'GRT';
    case TAXI = 'TXI';
    case TAKEOFF = 'TOF';
    case INIT_CLIM = 'ICL';
    case AIRBORNE = 'TKO';
    case ENROUTE = 'ENR';
    case DIVERTED = 'DV';
    case APPROACH = 'TEN';
    case APPROACH_ICAO = 'APR';
    case ON_FINAL = 'FIN';
    case LANDING = 'LDG';
    case LANDED = 'LAN';
    case ON_BLOCK = 'ONB';
    case ARRIVED = 'ARR';
    case CANCELLED = 'DX';
    case EMERG_DESCENT = 'EMG';
    case PAUSED = 'PSD';

    public function getLabel(): string
    {
        return match ($this) {
            self::INITIATED    => __('pireps.status.initialized'),
            self::SCHEDULED    => __('pireps.status.scheduled'),
            self::BOARDING     => __('pireps.status.boarding'),
            self::RDY_START    => __('pireps.status.ready_start'),
            self::PUSHBACK_TOW => __('pireps.status.push_tow'),
            self::DEPARTED     => __('pireps.status.departed'),
            self::RDY_DEICE    => __('pireps.status.ready_deice'),
            self::STRT_DEICE   => __('pireps.status.deicing'),
            self::GRND_RTRN    => __('pireps.status.ground_ret'),
            self::TAXI         => __('pireps.status.taxi'),
            self::TAKEOFF      => __('pireps.status.takeoff'),
            self::INIT_CLIM    => __('pireps.status.initial_clb'),
            self::AIRBORNE,
            self::ENROUTE  => __('pireps.status.enroute'),
            self::DIVERTED => __('pireps.status.diverted'),
            self::APPROACH,
            self::APPROACH_ICAO => __('pireps.status.approach'),
            self::ON_FINAL      => __('pireps.status.final_appr'),
            self::LANDING       => __('pireps.status.landing'),
            self::LANDED        => __('pireps.status.landed'),
            self::ON_BLOCK,
            self::ARRIVED       => __('pireps.status.arrived'),
            self::CANCELLED     => __('pireps.status.cancelled'),
            self::EMERG_DESCENT => __('pireps.status.emerg_decent'),
            self::PAUSED        => __('pireps.status.paused'),
        };
    }
}
