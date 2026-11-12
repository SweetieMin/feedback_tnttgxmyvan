<?php

namespace Tests\Unit\Models;

use App\Enums\MessageVisibility;
use App\Enums\UserRole;
use App\Models\Feedback;
use App\Models\FeedbackMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackMessageVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_only_sees_public_messages(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $feedback = Feedback::factory()->create(['user_id' => $employee->id]);

        FeedbackMessage::factory()->create(['feedback_id' => $feedback->id, 'visibility' => MessageVisibility::Public]);
        FeedbackMessage::factory()->internal()->create(['feedback_id' => $feedback->id]);

        $visible = $feedback->messages()->visibleTo($employee)->get();

        $this->assertCount(1, $visible);
        $this->assertTrue($visible->first()->visibility === MessageVisibility::Public);
    }

    public function test_handler_and_admin_see_both_public_and_internal_messages(): void
    {
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $feedback = Feedback::factory()->create();

        FeedbackMessage::factory()->create(['feedback_id' => $feedback->id, 'visibility' => MessageVisibility::Public]);
        FeedbackMessage::factory()->internal()->create(['feedback_id' => $feedback->id]);

        $this->assertCount(2, $feedback->messages()->visibleTo($handler)->get());
        $this->assertCount(2, $feedback->messages()->visibleTo($admin)->get());
    }
}
