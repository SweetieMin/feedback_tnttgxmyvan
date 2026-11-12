<?php

namespace Tests\Unit\Services;

use App\Enums\FeedbackStatus;
use App\Enums\MessageVisibility;
use App\Enums\UserRole;
use App\Exceptions\FeedbackConflictException;
use App\Models\Feedback;
use App\Models\FeedbackMessage;
use App\Models\User;
use App\Notifications\FeedbackPushNotification;
use App\Services\FeedbackStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FeedbackStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    private FeedbackStatusService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new FeedbackStatusService;
    }

    public function test_self_assign_moves_new_feedback_to_reviewing(): void
    {
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create(['status' => FeedbackStatus::New]);

        $result = $this->service->selfAssign($feedback, $handler);

        $this->assertSame($handler->id, $result->assigned_to);
        $this->assertSame(FeedbackStatus::Reviewing, $result->status);
        $this->assertDatabaseHas('feedback_activities', [
            'feedback_id' => $feedback->id,
            'type' => 'assignment_change',
            'to_value' => (string) $handler->id,
        ]);
    }

    public function test_self_assign_throws_when_already_assigned(): void
    {
        $handlerA = User::factory()->create(['role' => UserRole::Handler]);
        $handlerB = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create(['status' => FeedbackStatus::Reviewing, 'assigned_to' => $handlerA->id]);

        $this->expectException(FeedbackConflictException::class);

        $this->service->selfAssign($feedback, $handlerB);
    }

    public function test_self_assign_throws_for_closed_feedback_without_assignee(): void
    {
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create(['status' => FeedbackStatus::Closed]);

        try {
            $this->service->selfAssign($feedback, $handler);
            $this->fail('Expected FeedbackConflictException.');
        } catch (FeedbackConflictException) {
            $this->assertSame(FeedbackStatus::Closed, $feedback->refresh()->status);
            $this->assertNull($feedback->assigned_to);
        }
    }

    public function test_allowed_transitions_follow_the_forward_table(): void
    {
        $this->assertSame(
            [FeedbackStatus::InProgress, FeedbackStatus::Closed],
            $this->service->allowedTransitions(Feedback::factory()->make(['status' => FeedbackStatus::Reviewing])),
        );
        $this->assertSame([], $this->service->allowedTransitions(Feedback::factory()->make(['status' => FeedbackStatus::Resolved])));
    }

    public function test_change_status_moves_reviewing_to_in_progress(): void
    {
        Notification::fake();

        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create(['status' => FeedbackStatus::Reviewing, 'assigned_to' => $handler->id]);

        $result = $this->service->changeStatus($feedback, $handler, FeedbackStatus::InProgress);

        $this->assertSame(FeedbackStatus::InProgress, $result->status);
        $this->assertDatabaseHas('feedback_notifications', [
            'user_id' => $feedback->user_id,
            'type' => 'status_changed',
        ]);
        Notification::assertSentTo(
            $feedback->sender,
            FeedbackPushNotification::class,
            fn ($notification) => $notification->event === 'status_changed',
        );
    }

    public function test_change_status_rejects_invalid_transition(): void
    {
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create(['status' => FeedbackStatus::New]);

        $this->expectException(FeedbackConflictException::class);

        // new -> resolved không có trong bảng chuyển trạng thái (3.1).
        $this->service->changeStatus($feedback, $handler, FeedbackStatus::Resolved);
    }

    public function test_change_status_to_resolved_requires_a_public_reply(): void
    {
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create(['status' => FeedbackStatus::InProgress, 'assigned_to' => $handler->id]);

        $this->expectException(FeedbackConflictException::class);

        $this->service->changeStatus($feedback, $handler, FeedbackStatus::Resolved);
    }

    public function test_change_status_to_resolved_succeeds_with_a_public_reply(): void
    {
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create(['status' => FeedbackStatus::InProgress, 'assigned_to' => $handler->id]);

        FeedbackMessage::factory()->create([
            'feedback_id' => $feedback->id,
            'author_id' => $handler->id,
            'visibility' => MessageVisibility::Public,
        ]);

        $result = $this->service->changeStatus($feedback, $handler, FeedbackStatus::Resolved);

        $this->assertSame(FeedbackStatus::Resolved, $result->status);
        $this->assertNotNull($result->resolved_at);
    }

    public function test_change_status_to_closed_requires_reason_persisted(): void
    {
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create(['status' => FeedbackStatus::New]);

        $result = $this->service->changeStatus($feedback, $handler, FeedbackStatus::Closed, 'Trùng lặp');

        $this->assertSame(FeedbackStatus::Closed, $result->status);
        $this->assertSame('Trùng lặp', $result->closed_reason);
        $this->assertNotNull($result->closed_at);
    }

    public function test_reassign_moves_assignment_and_notifies_new_handler(): void
    {
        Notification::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $oldHandler = User::factory()->create(['role' => UserRole::Handler]);
        $newHandler = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create(['status' => FeedbackStatus::Reviewing, 'assigned_to' => $oldHandler->id]);

        $result = $this->service->reassign($feedback, $admin, $newHandler);

        $this->assertSame($newHandler->id, $result->assigned_to);
        $this->assertDatabaseHas('feedback_notifications', [
            'user_id' => $newHandler->id,
            'type' => 'assigned',
        ]);
        Notification::assertSentTo($newHandler, FeedbackPushNotification::class);
    }

    public function test_reassign_rejects_when_feedback_already_resolved(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $newHandler = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create(['status' => FeedbackStatus::Resolved]);

        $this->expectException(FeedbackConflictException::class);

        $this->service->reassign($feedback, $admin, $newHandler);
    }

    public function test_reopen_from_resolved_increments_count_and_notifies_handler(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create([
            'user_id' => $owner->id,
            'status' => FeedbackStatus::Resolved,
            'assigned_to' => $handler->id,
            'reopened_count' => 0,
        ]);

        $result = $this->service->reopen($feedback, $owner, 'Chưa đúng ý tôi');

        $this->assertSame(FeedbackStatus::InProgress, $result->status);
        $this->assertSame(1, $result->reopened_count);
        $this->assertDatabaseHas('feedback_notifications', [
            'user_id' => $handler->id,
            'type' => 'reopened',
        ]);
        Notification::assertSentTo($handler, FeedbackPushNotification::class);
    }

    public function test_reopen_rejects_feedback_that_is_still_open(): void
    {
        $owner = User::factory()->create();
        $feedback = Feedback::factory()->create(['user_id' => $owner->id, 'status' => FeedbackStatus::InProgress]);

        $this->expectException(FeedbackConflictException::class);

        $this->service->reopen($feedback, $owner, 'lý do');
    }
}
