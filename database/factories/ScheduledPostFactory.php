<?php

namespace Database\Factories;

use App\Enums\ScheduledPostStatus;
use App\Models\ScheduledPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ScheduledPost>
 */
class ScheduledPostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'idempotency_key' => (string) Str::uuid(),
            'content' => fake()->paragraph(),
            'scheduled_at' => now()->addDay(),
            'timezone' => 'UTC',
            'status' => ScheduledPostStatus::Pending,
        ];
    }
}
