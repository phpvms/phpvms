<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace Database\Factories;

use App\Contracts\Factory;
use App\Models\Airline;

/**
 * @extends Factory<Airline>
 */
class AirlineFactory extends Factory
{
    protected $model = Airline::class;

    /**
     * Defaults are shaped to look like real-world airline codes (both are
     * valid well under the form's 8-character max), so a factory-made
     * airline can be round-tripped through the admin form in a test without
     * being rewritten field by field:
     *
     *  - `icao` is exactly 3 characters, the real-world ICAO airline code
     *    length, where this used to emit a 5-character Hashids string. It is
     *    uniquely indexed on `airlines`, hence `unique()`.
     *  - `iata` is exactly 2, the real-world IATA airline code length, where
     *    it used to copy `icao`. Not unique in the schema, so it is not
     *    forced unique here -- only 676 two-letter codes exist and
     *    `unique()` would overflow on a large run.
     *  - `country` is a LOWERCASE alpha-2 code. `AirlineForm`'s country
     *    `Select` keys its options by `strtolower($item['alpha2'])`, so
     *    Faker's uppercase `countryCode()` matched no option and silently
     *    failed validation.
     */
    public function definition(): array
    {
        return [
            'icao'     => fake()->unique()->regexify('[A-Z]{3}'),
            'iata'     => fake()->regexify('[A-Z]{2}'),
            'name'     => fake()->company(),
            'country'  => strtolower(fake()->countryCode()),
            'active'   => 1,
            'low_cost' => false,
        ];
    }

    /**
     * Flag the airline as a low-cost carrier.
     */
    public function lowCost(): static
    {
        return $this->state(fn (array $attributes): array => [
            'low_cost' => true,
        ]);
    }
}
