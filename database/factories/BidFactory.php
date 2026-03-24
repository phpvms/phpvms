<?php

namespace Database\Factories;

use App\Models\Aircraft;
use App\Models\Bid;
use App\Models\Flight;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bid>
 */
class BidFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'     => User::factory(),
            'flight_id'   => Flight::factory(),
            'aircraft_id' => Aircraft::factory(),
        ];
    }
}
