<?php

namespace Tests\Feature;

use App\Enums\FeedbackStatus;
use App\Enums\MessageVisibility;
use App\Enums\UserRole;
use App\Models\Feedback;
use App\Models\FeedbackMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackStatusControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_handler_can_move_reviewing_to_in_progress(): void
    {
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create(['status' => FeedbackStatus::Reviewing, 'assigned_to' => $handler->id]);

        $this->actingAs($handler)
            ->patch(route('feedback.status.update', $feedback), ['to_status' => 'in_progress'])
            ->assertSessionHasNoErrors();

        $this->assertSame(FeedbackStatus::InProgress, $feedback->refresh()->status);
    }

    public function test_other_handler_cannot_change_status(): void
    {
        $assignedHandler = User::factory()->create(['role' => UserRole::Handler]);
        $otherHandler = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create(['status' => FeedbackStatus::Reviewing, 'assigned_to' => $assignedHandler->id]);

        $this->actingAs($otherHandler)
            ->patch(route('feedback.status.update', $feedback), ['to_status' => 'in_progress'])
            ->assertForbidden();
    }

    public function test_admin_can_change_status_without_being_assigned(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create(['status' => FeedbackStatus::Reviewing, 'assigned_to' => $handler->id]);

        $this->actingAs($admin)
            ->patch(route('feedback.status.update', $feedback), ['to_status' => 'in_progress'])
            ->assertSessionHasNoErrors();
    }

    public function test_invalid_to_status_value_is_rejected(): void
    {
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create(['status' => FeedbackStatus::Reviewing, 'assigned_to' => $handler->id]);

        $this->actingAs($handler)
            ->patch(route('feedback.status.update', $feedback), ['to_status' => 'new'])
            ->assertSessionHasErrors('to_status');
    }

    public function test_closing_requires_a_reason(): void
    {
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create(['status' => FeedbackStatus::New, 'assigned_to' => $handler->id]);

        $this->actingAs($handler)
            ->patch(route('feedback.status.update', $feedback), ['to_status' => 'closed'])
            ->assertSessionHasErrors('reason');
    }

    public function test_bypassing_ui_with_an_invalid_transition_reports_conflict_not_422(): void
    {
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create(['status' => FeedbackStatus::New, 'assigned_to' => $handler->id]);

        // new -> in_progress không hợp lệ (phải qua self-assign trước), dù
        // request hợp lệ về mặt validate (to_status nằm trong whitelist).
        $this->actingAs($handler)
            ->patch(route('feedback.status.update', $feedback), ['to_status' => 'in_progress'])
            ->assertSessionHasErrors('conflict');

        $this->assertSame(FeedbackStatus::New, $feedback->refresh()->status);
    }

    public function test_resolving_without_a_public_reply_reports_conflict(): void
    {
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create(['status' => FeedbackStatus::InProgress, 'assigned_to' => $handler->id]);

        $this->actingAs($handler)
            ->patch(route('feedback.status.update', $feedback), ['to_status' => 'resolved'])
            ->assertSessionHasErrors('conflict');
    }

    public function test_owner_can_reopen_resolved_feedback(): void
    {
        $owner = User::factory()->create();
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create([
            'user_id' => $owner->id,
            'status' => FeedbackStatus::InProgress,
            'assigned_to' => $handler->id,
        ]);
        FeedbackMessage::factory()->create([
            'feedback_id' => $feedback->id,
            'author_id' => $handler->id,
            'visibility' => MessageVisibility::Public,
        ]);
        $this->actingAs($handler)->patch(route('feedback.status.update', $feedback), ['to_status' => 'resolved']);

        $this->actingAs($owner)
            ->post(route('feedback.reopen', $feedback), ['reason' => 'Chưa đúng ý tôi'])
            ->assertSessionHasNoErrors();

        $this->assertSame(FeedbackStatus::InProgress, $feedback->refresh()->status);
    }

    public function test_employee_cannot_reopen_a_closed_feedback_only_admin_can(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $feedback = Feedback::factory()->create([
            'user_id' => $owner->id,
            'status' => FeedbackStatus::Closed,
            'closed_reason' => 'Trùng lặp',
        ]);

        $this->actingAs($owner)
            ->post(route('feedback.reopen', $feedback), ['reason' => 'Mở lại'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('feedback.reopen', $feedback), ['reason' => 'Mở lại'])
            ->assertSessionHasNoErrors();

        // Bảng 3.1: closed mở lại quay về reviewing, không phải in_progress.
        $this->assertSame(FeedbackStatus::Reviewing, $feedback->refresh()->status);
    }

    public function test_other_employee_cannot_reopen_feedback(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $feedback = Feedback::factory()->create(['user_id' => $owner->id, 'status' => FeedbackStatus::Resolved]);

        $this->actingAs($other)
            ->post(route('feedback.reopen', $feedback), ['reason' => 'Không liên quan'])
            ->assertForbidden();
    }
}
