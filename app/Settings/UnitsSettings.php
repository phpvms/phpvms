<?php

declare(strict_types=1);

namespace App\Settings;

use App\Enums\AltitudeUnit;
use App\Enums\DistanceUnit;
use App\Enums\SpeedUnit;
use App\Enums\TemperatureUnit;
use App\Enums\VolumeUnit;
use App\Enums\WeightUnit;
use Override;
use Spatie\LaravelSettings\Settings;

/**
 * The units values are displayed in, and the journal currency.
 *
 * Labels and descriptions: `resources/lang/en/settings.php` under `units`.
 */
final class UnitsSettings extends Settings
{
    public string $currency = 'USD';

    public DistanceUnit $distance = DistanceUnit::NAUTICAL_MILES;

    public WeightUnit $weight = WeightUnit::POUNDS;

    public SpeedUnit $speed = SpeedUnit::KNOTS;

    public AltitudeUnit $altitude = AltitudeUnit::FEET;

    public WeightUnit $fuel = WeightUnit::POUNDS;

    public VolumeUnit $volume = VolumeUnit::GALLONS;

    public TemperatureUnit $temperature = TemperatureUnit::FAHRENHEIT;

    #[Override]
    public static function group(): string
    {
        return 'units';
    }
}
