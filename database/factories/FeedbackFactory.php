<?php

namespace Database\Factories;

use App\Enums\FeedbackStatus;
use App\Models\Category;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Feedback>
 */
class FeedbackFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $paragraph = fake()->paragraph();

        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'assigned_to' => null,
            'title' => fake()->sentence(6),
            'content' => [
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            ['type' => 'text', 'text' => $paragraph],
                        ],
                    ],
                ],
            ],
            'excerpt' => Str::limit($paragraph, 160),
            'status' => FeedbackStatus::New,
        ];
    }
}
