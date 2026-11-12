<?php

namespace Tests\Feature;

use App\Enums\FeedbackStatus;
use App\Enums\UserRole;
use App\Models\Feedback;
use App\Models\FeedbackActivity;
use App\Models\User;
use Database\Seeders\FeedbackDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FeedbackDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_covers_every_role_and_status_with_a_consistent_timeline(): void
    {
        $this->seed(FeedbackDemoSeeder::class);

        foreach (UserRole::cases() as $role) {
            $this->assertTrue(User::where('role', $role->value)->exists(), "Thiếu vai trò {$role->value}");
        }

        foreach (FeedbackStatus::cases() as $status) {
            $this->assertTrue(Feedback::where('status', $status->value)->exists(), "Thiếu trạng thái {$status->value}");
        }

        $this->assertTrue(Hash::check('password', User::where('email', 'admin@tnttgxmyvan.org')->firstOrFail()->password));
        $this->assertTrue(Feedback::where('reopened_count', '>', 0)->exists());

        foreach (Feedback::all() as $feedback) {
            foreach (['first_response_at', 'resolved_at', 'closed_at'] as $column) {
                if ($feedback->{$column} !== null) {
                    $this->assertTrue(
                        $feedback->{$column}->greaterThanOrEqualTo($feedback->created_at),
                        "{$column} của góp ý #{$feedback->id} sớm hơn lúc gửi",
                    );
                }
            }

            $this->assertFalse(
                FeedbackActivity::where('feedback_id', $feedback->id)->where('created_at', '<', $feedback->created_at)->exists(),
                "Góp ý #{$feedback->id} có lịch sử trước lúc gửi",
            );
        }

        $this->assertLessThanOrEqual(now(), Feedback::max('created_at'));
    }
}
