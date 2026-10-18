<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class BrandProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::query()->where('email', 'test@example.com')->first();

        if ($user !== null && ! $user->brandProfile()->exists()) {
            $user->brandProfile()->create([
                'brand_name' => 'PostPilot Demo',
                'description' => 'A sample brand profile for trying the PostPilot workspace.',
                'industry' => 'Software',
                'target_audience' => 'Small business owners and creators',
                'preferred_tone' => 'Friendly and practical',
                'preferred_languages' => ['English'],
                'content_guidelines' => 'Use clear, helpful language.',
                'avoid_terms' => 'Avoid guarantees and unsupported performance claims.',
            ]);
        }
    }
}
