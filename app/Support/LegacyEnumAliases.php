<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\AcarsType;
use App\Enums\ActiveState;
use App\Enums\AircraftState;
use App\Enums\AircraftStatus;
use App\Enums\AirframeSource;
use App\Enums\ExpenseType;
use App\Enums\FareType;
use App\Enums\FlightType;
use App\Enums\FuelType;
use App\Enums\ImportExportType;
use App\Enums\JournalType;
use App\Enums\NavaidType;
use App\Enums\NavigationGroup;
use App\Enums\PageType;
use App\Enums\PirepFieldSource;
use App\Enums\PirepPhase;
use App\Enums\PirepSource;
use App\Enums\PirepState;
use App\Enums\UserState;

/**
 * Resolves the 7.x App\Models\Enums\* names to their 8.x classes, so 7.x themes and modules still load.
 *
 * @deprecated use App\Enums\* (and App\Support\Days); removed in a later major
 */
final class LegacyEnumAliases
{
    private const string PREFIX = 'App\\Models\\Enums\\';

    private const array MOVED = [
        'AcarsType'        => AcarsType::class,
        'ActiveState'      => ActiveState::class,
        'AircraftState'    => AircraftState::class,
        'AircraftStatus'   => AircraftStatus::class,
        'AirframeSource'   => AirframeSource::class,
        'Days'             => Days::class,
        'ExpenseType'      => ExpenseType::class,
        'FareType'         => FareType::class,
        'FlightType'       => FlightType::class,
        'FuelType'         => FuelType::class,
        'ImportExportType' => ImportExportType::class,
        'JournalType'      => JournalType::class,
        'NavaidType'       => NavaidType::class,
        'NavigationGroup'  => NavigationGroup::class,
        'PageType'         => PageType::class,
        'PirepFieldSource' => PirepFieldSource::class,
        'PirepSource'      => PirepSource::class,
        'PirepState'       => PirepState::class,
        'PirepStatus'      => PirepPhase::class,
        'UserState'        => UserState::class,
    ];

    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        // Lazy: an alias is only made when old code first names the class.
        spl_autoload_register(static function (string $class): void {
            if (!str_starts_with($class, self::PREFIX)) {
                return;
            }

            $target = self::MOVED[substr($class, strlen(self::PREFIX))] ?? null;

            if ($target !== null) {
                class_alias($target, $class);
            }
        });
    }
}
