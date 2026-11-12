<?php

namespace Database\Factories;

use App\Enums\ActivityType;
use App\Models\Feedback;
use App\Models\FeedbackActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeedbackActivity>
 */
class FeedbackActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'feedback_id' => Feedback::factory(),
            'actor_id' => User::factory(),
            'type' => ActivityType::StatusChange,
            'from_value' => null,
            'to_value' => 'new',
            'note' => null,
        ];
    }
}
