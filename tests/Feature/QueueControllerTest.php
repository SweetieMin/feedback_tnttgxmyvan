<?php

namespace Tests\Feature;

use App\Enums\FeedbackStatus;
use App\Enums\UserRole;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QueueControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_handler_sees_the_whole_queue_and_can_filter_unassigned(): void
    {
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $unassigned = Feedback::factory()->create();
        Feedback::factory()->create(['status' => FeedbackStatus::Reviewing, 'assigned_to' => $handler->id]);

        $this->actingAs($handler)
            ->get(route('queue.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('feedback/queue')
                ->where('scope', 'handler')
                ->has('feedbacks', 2)
                ->has('feedbacks.0.sender'),
            );

        $this->actingAs($handler)
            ->get(route('queue.index', ['assignee' => 'unassigned']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('feedbacks', 1)
                ->where('feedbacks.0.id', $unassigned->id),
            );
    }

    public function test_admin_feedback_page_filters_by_any_assignee(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $handlerA = User::factory()->create(['role' => UserRole::Handler]);
        $handlerB = User::factory()->create(['role' => UserRole::Handler]);
        $ofA = Feedback::factory()->create(['status' => FeedbackStatus::Reviewing, 'assigned_to' => $handlerA->id]);
        Feedback::factory()->create(['status' => FeedbackStatus::Reviewing, 'assigned_to' => $handlerB->id]);

        $this->actingAs($admin)
            ->get(route('admin.feedback.index', ['assignee' => $handlerA->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('feedback/queue')
                ->where('scope', 'admin')
                ->has('feedbacks', 1)
                ->where('feedbacks.0.id', $ofA->id)
                ->has('handlers', 2),
            );
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('queue.index'))->assertRedirect(route('login'));
        $this->get(route('admin.feedback.index'))->assertRedirect(route('login'));
    }

    public function test_employee_cannot_view_queue_or_admin_feedback(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee]);

        $this->actingAs($employee)->get(route('queue.index'))->assertForbidden();
        $this->actingAs($employee)->get(route('admin.feedback.index'))->assertForbidden();
    }

    public function test_handler_cannot_view_admin_feedback(): void
    {
        $handler = User::factory()->create(['role' => UserRole::Handler]);

        $this->actingAs($handler)->get(route('admin.feedback.index'))->assertForbidden();
    }
}
