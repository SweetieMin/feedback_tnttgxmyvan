<?php

namespace Database\Factories;

use App\Enums\MessageVisibility;
use App\Models\Feedback;
use App\Models\FeedbackMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeedbackMessage>
 */
class FeedbackMessageFactory extends Factory
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
            'author_id' => User::factory(),
            'body' => [
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            ['type' => 'text', 'text' => fake()->sentence()],
                        ],
                    ],
                ],
            ],
            'visibility' => MessageVisibility::Public,
        ];
    }

    /**
     * Indicate that this message is an internal note, hidden from the employee.
     */
    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => MessageVisibility::Internal,
        ]);
    }
}
