<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace Database\Factories;

use App\Contracts\Factory;
use App\Models\Airline;
use App\Models\Enums\UserState;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    private static string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        if (!isset(self::$password) || (self::$password === '' || self::$password === '0')) {
            self::$password = Hash::make('secret');
        }

        return [
            'id'                => null,
            'pilot_id'          => null,
            'name'              => fake()->name,
            'email'             => fake()->safeEmail,
            'password'          => self::$password,
            'api_key'           => fake()->sha1,
            'airline_id'        => fn () => Airline::factory()->create()->id,
            'rank_id'           => 1,
            'flights'           => fake()->numberBetween(0, 1000),
            'flight_time'       => fake()->numberBetween(0, 10000),
            'transfer_time'     => fake()->numberBetween(0, 10000),
            'state'             => UserState::ACTIVE,
            'remember_token'    => fake()->unique()->text(5),
            'email_verified_at' => now(),
            'avatar_url'        => '',
        ];
    }
}
