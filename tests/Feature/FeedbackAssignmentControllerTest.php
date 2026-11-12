<?php

namespace Tests\Feature;

use App\Enums\FeedbackStatus;
use App\Enums\UserRole;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackAssignmentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_handler_can_self_assign_unassigned_feedback(): void
    {
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create(['status' => FeedbackStatus::New]);

        $this->actingAs($handler)
            ->post(route('feedback.self-assign', $feedback))
            ->assertSessionHasNoErrors();

        $feedback->refresh();

        $this->assertSame($handler->id, $feedback->assigned_to);
        $this->assertSame(FeedbackStatus::Reviewing, $feedback->status);
    }

    public function test_admin_cannot_self_assign(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $feedback = Feedback::factory()->create(['status' => FeedbackStatus::New]);

        $this->actingAs($admin)
            ->post(route('feedback.self-assign', $feedback))
            ->assertForbidden();
    }

    public function test_employee_cannot_self_assign(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $feedback = Feedback::factory()->create(['status' => FeedbackStatus::New]);

        $this->actingAs($employee)
            ->post(route('feedback.self-assign', $feedback))
            ->assertForbidden();
    }

    public function test_self_assign_reports_conflict_once_already_assigned(): void
    {
        $handlerA = User::factory()->create(['role' => UserRole::Handler]);
        $handlerB = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create(['status' => FeedbackStatus::Reviewing, 'assigned_to' => $handlerA->id]);

        $this->actingAs($handlerB)
            ->from(route('queue.index'))
            ->post(route('feedback.self-assign', $feedback))
            ->assertRedirect(route('queue.index'))
            ->assertSessionHasErrors('conflict');

        $this->assertSame($handlerA->id, $feedback->refresh()->assigned_to);
    }

    public function test_self_assign_reports_conflict_for_closed_feedback_without_assignee(): void
    {
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create(['status' => FeedbackStatus::Closed]);

        $this->actingAs($handler)
            ->post(route('feedback.self-assign', $feedback))
            ->assertSessionHasErrors('conflict');

        $this->assertSame(FeedbackStatus::Closed, $feedback->refresh()->status);
        $this->assertNull($feedback->assigned_to);
    }

    public function test_admin_can_reassign_to_another_handler(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $oldHandler = User::factory()->create(['role' => UserRole::Handler]);
        $newHandler = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create(['status' => FeedbackStatus::Reviewing, 'assigned_to' => $oldHandler->id]);

        $this->actingAs($admin)
            ->patch(route('feedback.reassign', $feedback), ['assigned_to' => $newHandler->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($newHandler->id, $feedback->refresh()->assigned_to);
    }

    public function test_handler_cannot_reassign(): void
    {
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $newHandler = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create(['status' => FeedbackStatus::Reviewing]);

        $this->actingAs($handler)
            ->patch(route('feedback.reassign', $feedback), ['assigned_to' => $newHandler->id])
            ->assertForbidden();
    }

    public function test_reassign_target_must_have_handler_role(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $feedback = Feedback::factory()->create(['status' => FeedbackStatus::Reviewing]);

        $this->actingAs($admin)
            ->patch(route('feedback.reassign', $feedback), ['assigned_to' => $employee->id])
            ->assertSessionHasErrors('assigned_to');
    }
}
