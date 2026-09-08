<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Theme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Theme>
 */
class ThemeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rebrickable_id' => fake()->unique()->numberBetween(1, 1000000),
            'name' => fake()->words(2, true),
            'parent_id' => null,
        ];
    }
}
