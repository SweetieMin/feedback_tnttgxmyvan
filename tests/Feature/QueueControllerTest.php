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

    public function test_queue_is_split_into_open_resolved_and_closed_tabs(): void
    {
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        Feedback::factory()->create(['status' => FeedbackStatus::New]);
        Feedback::factory()->create(['status' => FeedbackStatus::Reviewing]);
        Feedback::factory()->create(['status' => FeedbackStatus::InProgress]);
        $resolved = Feedback::factory()->create(['status' => FeedbackStatus::Resolved]);
        $closed = Feedback::factory()->count(2)->create(['status' => FeedbackStatus::Closed]);

        $counts = ['open' => 3, 'resolved' => 1, 'closed' => 2];

        $this->actingAs($handler)
            ->get(route('queue.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'open')
                ->where('tabCounts', $counts)
                ->has('feedbacks', 3)
                ->where('feedbacks', fn ($feedbacks) => collect($feedbacks)
                    ->pluck('status')
                    ->diff(['new', 'reviewing', 'in_progress'])
                    ->isEmpty()),
            );

        $this->actingAs($handler)
            ->get(route('queue.index', ['tab' => 'resolved']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'resolved')
                ->where('tabCounts', $counts)
                ->has('feedbacks', 1)
                ->where('feedbacks.0.id', $resolved->id),
            );

        $this->actingAs($handler)
            ->get(route('queue.index', ['tab' => 'closed']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'closed')
                ->has('feedbacks', 2)
                ->where('feedbacks', fn ($feedbacks) => collect($feedbacks)
                    ->pluck('id')
                    ->diff($closed->pluck('id'))
                    ->isEmpty()),
            );
    }

    public function test_status_filter_picks_its_tab_and_is_ignored_outside_it(): void
    {
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        Feedback::factory()->create(['status' => FeedbackStatus::New]);
        Feedback::factory()->create(['status' => FeedbackStatus::Reviewing]);
        $closed = Feedback::factory()->create(['status' => FeedbackStatus::Closed]);

        // Liên kết từ dashboard chỉ mang `status` — phải mở đúng tab.
        $this->actingAs($handler)
            ->get(route('queue.index', ['status' => 'closed']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'closed')
                ->where('filters.status', 'closed')
                ->has('feedbacks', 1)
                ->where('feedbacks.0.id', $closed->id),
            );

        $this->actingAs($handler)
            ->get(route('queue.index', ['status' => 'new']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'open')
                ->has('feedbacks', 1)
                ->where('feedbacks.0.status', 'new'),
            );

        // `status` lệch tab hoặc không hợp lệ thì bị bỏ qua.
        $this->actingAs($handler)
            ->get(route('queue.index', ['tab' => 'closed', 'status' => 'new']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'closed')
                ->missing('filters.status')
                ->has('feedbacks', 1)
                ->where('feedbacks.0.id', $closed->id),
            );

        $this->actingAs($handler)
            ->get(route('queue.index', ['tab' => 'bogus', 'status' => 'bogus']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'open')
                ->missing('filters.status')
                ->has('feedbacks', 2),
            );
    }

    public function test_tab_counts_follow_assignee_and_category_filters(): void
    {
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        Feedback::factory()->create(['status' => FeedbackStatus::InProgress, 'assigned_to' => $handler->id]);
        Feedback::factory()->create(['status' => FeedbackStatus::Closed, 'assigned_to' => $handler->id]);
        Feedback::factory()->create(['status' => FeedbackStatus::New]);
        Feedback::factory()->create(['status' => FeedbackStatus::Resolved]);

        $this->actingAs($handler)
            ->get(route('queue.index', ['assignee' => 'me']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tabCounts', ['open' => 1, 'resolved' => 0, 'closed' => 1])
                ->has('feedbacks', 1),
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
