<?php

namespace App\Enums;

use App\Enums\Concerns\HasSelect;
use Filament\Support\Contracts\HasLabel;

enum FlightType: string implements HasLabel
{
    use HasSelect;

    case SCHED_PAX = 'J';
    case SCHED_CARGO = 'F';
    case CHARTER_PAX_ONLY = 'C';
    case ADDITIONAL_CARGO = 'A';
    case VIP = 'E';
    case ADDTL_PAX = 'G';
    case CHARTER_CARGO_MAIL = 'H';
    case AMBULANCE = 'I';
    case TRAINING = 'K';
    case MAIL_SERVICE = 'M';
    case CHARTER_SPECIAL = 'O';
    case POSITIONING = 'P';
    case TECHNICAL_TEST = 'T';
    case MILITARY = 'W';
    case TECHNICAL_STOP = 'X';
    case SHUTTLE = 'S';
    case ADDTL_SHUTTLE = 'B';
    case CARGO_IN_CABIN = 'Q';
    case ADDTL_CARGO_IN_CABIN = 'R';
    case CHARTER_CARGO_IN_CABIN = 'L';
    case GENERAL_AVIATION = 'D';
    case AIR_TAXI = 'N';
    case COMPANY_SPECIFIC = 'Y';
    case OTHER = 'Z';

    public function getLabel(): string
    {
        return match ($this) {
            self::SCHED_PAX              => __('flights.type.pass_scheduled'),
            self::SCHED_CARGO            => __('flights.type.cargo_scheduled'),
            self::CHARTER_PAX_ONLY       => __('flights.type.charter_pass_only'),
            self::ADDITIONAL_CARGO       => __('flights.type.addtl_cargo_mail'),
            self::VIP                    => __('flights.type.special_vip'),
            self::ADDTL_PAX              => __('flights.type.pass_addtl'),
            self::CHARTER_CARGO_MAIL     => __('flights.type.charter_cargo'),
            self::AMBULANCE              => __('flights.type.ambulance'),
            self::TRAINING               => __('flights.type.training_flight'),
            self::MAIL_SERVICE           => __('flights.type.mail_service'),
            self::CHARTER_SPECIAL        => __('flights.type.charter_special'),
            self::POSITIONING            => __('flights.type.positioning'),
            self::TECHNICAL_TEST         => __('flights.type.technical_test'),
            self::MILITARY               => __('flights.type.military'),
            self::TECHNICAL_STOP         => __('flights.type.technical_stop'),
            self::SHUTTLE                => __('flights.type.shuttle'),
            self::ADDTL_SHUTTLE          => __('flights.type.addtl_shuttle'),
            self::CARGO_IN_CABIN         => __('flights.type.cargo_in_cabin'),
            self::ADDTL_CARGO_IN_CABIN   => __('flights.type.addtl_cargo_in_cabin'),
            self::CHARTER_CARGO_IN_CABIN => __('flights.type.charter_cargo_in_cabin'),
            self::GENERAL_AVIATION       => __('flights.type.general_aviation'),
            self::AIR_TAXI               => __('flights.type.air_taxi'),
            self::COMPANY_SPECIFIC       => __('flights.type.company_specific'),
            self::OTHER                  => __('flights.type.other'),
        };
    }
}
