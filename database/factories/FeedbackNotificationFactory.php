<?php

namespace Database\Factories;

use App\Models\Feedback;
use App\Models\FeedbackNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeedbackNotification>
 */
class FeedbackNotificationFactory extends Factory
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
            'feedback_id' => Feedback::factory(),
            'type' => 'status_changed',
            'data' => ['message' => fake()->sentence()],
            'read_at' => null,
        ];
    }
}
