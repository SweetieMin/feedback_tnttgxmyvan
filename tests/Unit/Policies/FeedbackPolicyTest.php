<?php

namespace Tests\Unit\Policies;

use App\Enums\FeedbackStatus;
use App\Enums\UserRole;
use App\Models\Feedback;
use App\Models\User;
use App\Policies\FeedbackPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackPolicyTest extends TestCase
{
    use RefreshDatabase;

    private FeedbackPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new FeedbackPolicy;
    }

    public function test_owner_can_view_own_feedback(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $feedback = Feedback::factory()->create(['user_id' => $employee->id]);

        $this->assertTrue($this->policy->view($employee, $feedback));
    }

    public function test_other_employee_cannot_view_feedback(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Employee]);
        $other = User::factory()->create(['role' => UserRole::Employee]);
        $feedback = Feedback::factory()->create(['user_id' => $owner->id]);

        $this->assertFalse($this->policy->view($other, $feedback));
    }

    public function test_handler_and_admin_can_view_any_feedback(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Employee]);
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $feedback = Feedback::factory()->create(['user_id' => $owner->id]);

        $this->assertTrue($this->policy->view($handler, $feedback));
        $this->assertTrue($this->policy->view($admin, $feedback));
    }

    public function test_owner_can_update_only_while_new(): void
    {
        $owner = User::factory()->create();
        $new = Feedback::factory()->create(['user_id' => $owner->id, 'status' => FeedbackStatus::New]);
        $reviewing = Feedback::factory()->create(['user_id' => $owner->id, 'status' => FeedbackStatus::Reviewing]);

        $this->assertTrue($this->policy->update($owner, $new));
        $this->assertFalse($this->policy->update($owner, $reviewing));
    }

    public function test_only_unassigned_feedback_can_be_self_assigned_by_handler(): void
    {
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $unassigned = Feedback::factory()->create(['status' => FeedbackStatus::New]);
        $assigned = Feedback::factory()->create(['status' => FeedbackStatus::Reviewing, 'assigned_to' => $handler->id]);

        $this->assertTrue($this->policy->selfAssign($handler, $unassigned));
        $this->assertFalse($this->policy->selfAssign($handler, $assigned));
        $this->assertFalse($this->policy->selfAssign($employee, $unassigned));

        $closedUnassigned = Feedback::factory()->create(['status' => FeedbackStatus::Closed]);
        $this->assertFalse($this->policy->selfAssign($handler, $closedUnassigned));
    }

    public function test_change_status_requires_being_assignee_or_admin(): void
    {
        $assignedHandler = User::factory()->create(['role' => UserRole::Handler]);
        $otherHandler = User::factory()->create(['role' => UserRole::Handler]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $feedback = Feedback::factory()->create(['status' => FeedbackStatus::Reviewing, 'assigned_to' => $assignedHandler->id]);

        $this->assertTrue($this->policy->changeStatus($assignedHandler, $feedback));
        $this->assertTrue($this->policy->changeStatus($admin, $feedback));
        $this->assertFalse($this->policy->changeStatus($otherHandler, $feedback));
    }

    public function test_only_admin_can_reassign_open_feedback(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $open = Feedback::factory()->create(['status' => FeedbackStatus::InProgress]);
        $resolved = Feedback::factory()->create(['status' => FeedbackStatus::Resolved]);

        $this->assertTrue($this->policy->reassign($admin, $open));
        $this->assertFalse($this->policy->reassign($admin, $resolved));
        $this->assertFalse($this->policy->reassign($handler, $open));
    }

    public function test_reopen_rules_differ_for_resolved_and_closed(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Employee]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $handler = User::factory()->create(['role' => UserRole::Handler]);

        $resolved = Feedback::factory()->create([
            'user_id' => $owner->id,
            'status' => FeedbackStatus::Resolved,
            'assigned_to' => $handler->id,
        ]);
        $closed = Feedback::factory()->create(['user_id' => $owner->id, 'status' => FeedbackStatus::Closed]);
        $inProgress = Feedback::factory()->create(['user_id' => $owner->id, 'status' => FeedbackStatus::InProgress]);

        $this->assertTrue($this->policy->reopen($owner, $resolved));
        $this->assertTrue($this->policy->reopen($handler, $resolved));
        $this->assertFalse($this->policy->reopen($owner, $closed));
        $this->assertTrue($this->policy->reopen($admin, $closed));
        $this->assertFalse($this->policy->reopen($owner, $inProgress));
    }
}
