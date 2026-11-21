<?php

declare(strict_types=1);

// IDE-only, never loaded: LegacyEnumAliases creates these names at runtime with class_alias.
// Declaring them here lets the IDE resolve them and mark them deprecated.

namespace App\Models\Enums;

use App\Enums\PirepPhase;

/** @deprecated use \App\Enums\AcarsType */
class AcarsType extends \App\Enums\AcarsType {}

/** @deprecated use \App\Enums\ActiveState */
class ActiveState extends \App\Enums\ActiveState {}

/** @deprecated use \App\Enums\AircraftState */
class AircraftState extends \App\Enums\AircraftState {}

/** @deprecated use \App\Enums\AircraftStatus */
class AircraftStatus extends \App\Enums\AircraftStatus {}

/** @deprecated use \App\Enums\AirframeSource */
class AirframeSource extends \App\Enums\AirframeSource {}

/** @deprecated use \App\Support\Days */
class Days extends \App\Support\Days {}

/** @deprecated use \App\Enums\ExpenseType */
class ExpenseType extends \App\Enums\ExpenseType {}

/** @deprecated use \App\Enums\FareType */
class FareType extends \App\Enums\FareType {}

/** @deprecated use \App\Enums\FlightType */
class FlightType extends \App\Enums\FlightType {}

/** @deprecated use \App\Enums\FuelType */
class FuelType extends \App\Enums\FuelType {}

/** @deprecated use \App\Enums\ImportExportType */
class ImportExportType extends \App\Enums\ImportExportType {}

/** @deprecated use \App\Enums\JournalType */
class JournalType extends \App\Enums\JournalType {}

/** @deprecated use \App\Enums\NavaidType */
class NavaidType extends \App\Enums\NavaidType {}

/** @deprecated use \App\Enums\NavigationGroup */
class NavigationGroup extends \App\Enums\NavigationGroup {}

/** @deprecated use \App\Enums\PageType */
class PageType extends \App\Enums\PageType {}

/** @deprecated use \App\Enums\PirepFieldSource */
class PirepFieldSource extends \App\Enums\PirepFieldSource {}

/** @deprecated use \App\Enums\PirepSource */
class PirepSource extends \App\Enums\PirepSource {}

/** @deprecated use \App\Enums\PirepState */
class PirepState extends \App\Enums\PirepState {}

/** @deprecated use \App\Enums\PirepPhase */
class PirepStatus extends PirepPhase {}

/** @deprecated use \App\Enums\UserState */
class UserState extends \App\Enums\UserState {}
