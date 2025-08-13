<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

namespace Database\Factories;

use App\Contracts\Factory;
use App\Models\Enums\ExpenseType;
use App\Models\Expense;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Expense::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id'           => null,
            'airline_id'   => null,
            'name'         => fake()->text(20),
            'amount'       => fake()->randomFloat(2, 100, 1000),
            'type'         => ExpenseType::FLIGHT,
            'multiplier'   => false,
            'ref_model'    => Expense::class,
            'ref_model_id' => null,
            'active'       => true,
        ];
    }
}
