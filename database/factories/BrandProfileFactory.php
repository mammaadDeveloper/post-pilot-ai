<?php

namespace Database\Factories;

use App\Models\BrandProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BrandProfile>
 */
class BrandProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'brand_name' => fake()->company(),
            'description' => fake()->paragraph(),
            'industry' => fake()->word(),
            'target_audience' => fake()->sentence(),
            'preferred_tone' => 'Friendly and knowledgeable',
            'preferred_languages' => ['English'],
            'content_guidelines' => fake()->sentence(),
            'avoid_terms' => null,
        ];
    }
}
