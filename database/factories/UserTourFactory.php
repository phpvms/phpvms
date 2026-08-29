<?php

declare(strict_types=1);

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace Database\Factories;

use App\Contracts\Factory;
use App\Features\Tour\Enums\TourStatus;
use App\Features\Tour\Models\UserTour;
use App\Models\FlightBundle;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<UserTour>
 */
class UserTourFactory extends Factory
{
    protected $model = UserTour::class;

    /**
     * A three-leg run that has not been flown yet.
     *
     * The roster carries synthesized flight ids, since `flight_id` is FK-less
     * and most tests never resolve it. Tests that do need real legs pass their
     * own `legs` / `flight_id`.
     */
    public function definition(): array
    {
        $legs = $this->roster(3);

        return [
            'user_id'        => User::factory(),
            'bundle_id'      => FlightBundle::factory(),
            'aircraft_id'    => null,
            'pirep_id'       => null,
            'flight_id'      => $legs[0]['flight_id'],
            'name'           => fake()->words(3, true),
            'description'    => fake()->optional()->sentence(),
            'status'         => TourStatus::InProgress,
            'legs_total'     => count($legs),
            'legs_completed' => 0,
            'legs'           => $legs,
            'started_at'     => Carbon::now(),
            'completed_at'   => null,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(['status' => TourStatus::InProgress, 'completed_at' => null]);
    }

    /** Every leg filed, so `flight_id` is null and there is nothing left to fly. */
    public function completed(): static
    {
        return $this->state(function (array $attributes): array {
            $legs = collect($attributes['legs'] ?? [])
                ->map(fn (array $leg): array => [
                    ...$leg,
                    'pirep_id' => $leg['pirep_id'] ?? Str::nanoid(),
                    'filed_at' => $leg['filed_at'] ?? Carbon::now()->toIso8601String(),
                ])
                ->all();

            return [
                'status'         => TourStatus::Completed,
                'legs'           => $legs,
                'legs_completed' => count($legs),
                'flight_id'      => null,
                'completed_at'   => Carbon::now(),
            ];
        });
    }

    /** Ended early, with whatever progress it had left intact. */
    public function cancelled(): static
    {
        return $this->state(['status' => TourStatus::Cancelled]);
    }

    /** Swept up by bid expiry rather than ended by anyone. */
    public function expired(): static
    {
        return $this->state(['status' => TourStatus::Expired]);
    }

    /**
     * Build an unflown roster of `$count` legs numbered from 1.
     *
     * @return list<array{flight_id: string, route_leg: int, pirep_id: null, filed_at: null}>
     */
    private function roster(int $count): array
    {
        return collect(range(1, $count))
            ->map(fn (int $leg): array => [
                'flight_id' => Str::nanoid(),
                'route_leg' => $leg,
                'pirep_id'  => null,
                'filed_at'  => null,
            ])
            ->all();
    }
}
