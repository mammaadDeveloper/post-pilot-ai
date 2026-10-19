<?php

namespace Database\Factories;

use App\Enums\PublicationTargetStatus;
use App\Models\PublicationTarget;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PublicationTarget>
 */
class PublicationTargetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scheduled_post_id' => ScheduledPost::factory(),
            'social_account_id' => SocialAccount::factory(),
            'status' => PublicationTargetStatus::Pending,
            'idempotency_key' => fake()->unique()->sha256(),
        ];
    }
}
