<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace App\Database\Factories;

use App\Contracts\Factory;
use App\Models\Airline;
use App\Models\Enums\UserState;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * @var string
     */
    private static string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        if (empty(self::$password)) {
            self::$password = Hash::make('secret');
        }

        return [
            'id'                => null,
            'pilot_id'          => null,
            'name'              => $this->faker->name,
            'email'             => $this->faker->safeEmail,
            'password'          => self::$password,
            'api_key'           => $this->faker->sha1,
            'airline_id'        => fn () => Airline::factory()->create()->id,
            'rank_id'           => 1,
            'flights'           => $this->faker->numberBetween(0, 1000),
            'flight_time'       => $this->faker->numberBetween(0, 10000),
            'transfer_time'     => $this->faker->numberBetween(0, 10000),
            'state'             => UserState::ACTIVE,
            'remember_token'    => $this->faker->unique()->text(5),
            'email_verified_at' => now(),
            'avatar'            => '',
        ];
    }
}
