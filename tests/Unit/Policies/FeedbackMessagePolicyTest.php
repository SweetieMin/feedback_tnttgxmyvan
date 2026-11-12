<?php

namespace Tests\Unit\Policies;

use App\Enums\MessageVisibility;
use App\Enums\UserRole;
use App\Models\Feedback;
use App\Models\User;
use App\Policies\FeedbackMessagePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackMessagePolicyTest extends TestCase
{
    use RefreshDatabase;

    private FeedbackMessagePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new FeedbackMessagePolicy;
    }

    public function test_owner_can_post_public_reply_but_not_internal_note(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Employee]);
        $feedback = Feedback::factory()->create(['user_id' => $owner->id]);

        $this->assertTrue($this->policy->create($owner, $feedback, MessageVisibility::Public));
        $this->assertFalse($this->policy->create($owner, $feedback, MessageVisibility::Internal));
    }

    public function test_handler_can_post_internal_note_on_any_feedback(): void
    {
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create();

        $this->assertTrue($this->policy->create($handler, $feedback, MessageVisibility::Internal));
    }

    public function test_employee_who_is_not_owner_cannot_post_at_all(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Employee]);
        $other = User::factory()->create(['role' => UserRole::Employee]);
        $feedback = Feedback::factory()->create(['user_id' => $owner->id]);

        $this->assertFalse($this->policy->create($other, $feedback, MessageVisibility::Public));
    }

    public function test_only_handler_and_admin_can_view_internal_notes(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertFalse($this->policy->viewInternal($employee));
        $this->assertTrue($this->policy->viewInternal($handler));
        $this->assertTrue($this->policy->viewInternal($admin));
    }
}
