<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\FeedbackStatus;
use App\Enums\MessageVisibility;
use App\Enums\UserRole;
use App\Models\Feedback;
use App\Models\FeedbackActivity;
use App\Models\FeedbackMessage;
use App\Models\User;
use App\Services\FeedbackStatusService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_sees_only_own_status_counts_and_recent_feedbacks(): void
    {
        $employee = User::factory()->create();

        Feedback::factory()->count(2)->create(['user_id' => $employee->id]);
        Feedback::factory()->count(4)->create(['user_id' => $employee->id, 'status' => FeedbackStatus::Resolved]);
        Feedback::factory()->count(3)->create();

        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('scope', 'employee')
                ->where('statusCounts', [
                    'new' => 2,
                    'reviewing' => 0,
                    'in_progress' => 0,
                    'resolved' => 4,
                    'closed' => 0,
                ])
                ->has('recentFeedbacks', 5)
                ->missing('workloadByAssignee')
                ->missing('newCount'),
            );
    }

    public function test_handler_sees_queue_wide_counts_and_longest_open_feedbacks(): void
    {
        $handler = User::factory()->create(['role' => UserRole::Handler]);

        $this->travelTo(now()->subDays(10));
        $oldest = Feedback::factory()->create(['title' => 'Cũ nhất']);
        $this->travelBack();

        Feedback::factory()->create(['status' => FeedbackStatus::New]);
        Feedback::factory()->create(['status' => FeedbackStatus::InProgress, 'assigned_to' => $handler->id]);
        Feedback::factory()->create(['status' => FeedbackStatus::Resolved]);
        Feedback::factory()->create(['status' => FeedbackStatus::Closed]);

        $this->actingAs($handler)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('scope', 'handler')
                ->where('newCount', 2)
                // 2 góp ý "new" chưa giao; resolved/closed không tính dù chưa giao.
                ->where('unassignedCount', 2)
                ->where('statusCounts.resolved', 1)
                ->where('statusCounts.closed', 1)
                ->has('longestOpen', 3)
                ->where('longestOpen.0.id', $oldest->id)
                ->where('longestOpen.0.daysOpen', 10)
                ->missing('workloadByAssignee'),
            );
    }

    public function test_admin_new_count_matches_rows_with_status_new(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Feedback::factory()->count(3)->create();
        Feedback::factory()->create(['status' => FeedbackStatus::Reviewing]);

        $expected = Feedback::where('status', FeedbackStatus::New->value)->count();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('scope', 'admin')
                ->where('statusCounts.new', $expected),
            );
    }

    public function test_admin_workload_lists_every_handler_including_ones_without_data(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $busy = User::factory()->create(['role' => UserRole::Handler, 'name' => 'A Busy']);
        $idle = User::factory()->create(['role' => UserRole::Handler, 'name' => 'B Idle']);

        Feedback::factory()->create(['status' => FeedbackStatus::Reviewing, 'assigned_to' => $busy->id]);
        Feedback::factory()->create(['status' => FeedbackStatus::InProgress, 'assigned_to' => $busy->id]);

        $resolvedRecently = Feedback::factory()->create(['status' => FeedbackStatus::Resolved, 'assigned_to' => $busy->id]);
        $this->logResolved($resolvedRecently, $busy, now()->subDays(5));

        // Ngoài cửa sổ 30 ngày → không được tính vào resolved30d.
        $resolvedLongAgo = Feedback::factory()->create(['status' => FeedbackStatus::Resolved, 'assigned_to' => $busy->id]);
        $this->logResolved($resolvedLongAgo, $busy, now()->subDays(40));

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('workloadByAssignee', 2)
                ->where('workloadByAssignee.0', [
                    'id' => $busy->id,
                    'name' => 'A Busy',
                    'inProgress' => 2,
                    'resolved30d' => 1,
                ])
                ->where('workloadByAssignee.1', [
                    'id' => $idle->id,
                    'name' => 'B Idle',
                    'inProgress' => 0,
                    'resolved30d' => 0,
                ]),
            );
    }

    public function test_reopened_then_resolved_again_counts_twice_in_their_own_weeks(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $service = app(FeedbackStatusService::class);

        // Thứ Tư tuần ISO 45/2026 (tuần bắt đầu 02/11).
        $this->travelTo(Carbon::parse('2026-11-04 10:00:00'));
        $feedback = Feedback::factory()->create(['status' => FeedbackStatus::InProgress, 'assigned_to' => $handler->id]);
        FeedbackMessage::factory()->create([
            'feedback_id' => $feedback->id,
            'author_id' => $handler->id,
            'visibility' => MessageVisibility::Public,
        ]);

        $this->travelTo(Carbon::parse('2026-11-04 12:00:00'));
        $service->changeStatus($feedback, $handler, FeedbackStatus::Resolved);

        // Thứ Tư tuần ISO 46/2026 (tuần bắt đầu 09/11): mở lại rồi giải quyết lại.
        $this->travelTo(Carbon::parse('2026-11-11 10:00:00'));
        $service->reopen($feedback->refresh(), $feedback->sender, 'Chưa hài lòng');
        $service->changeStatus($feedback->refresh(), $handler, FeedbackStatus::Resolved);

        $this->travelTo(Carbon::parse('2026-11-12 09:00:00'));

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('weeklyTrend', 8)
                ->where('weeklyTrend.6.weekStart', '2026-11-02')
                ->where('weeklyTrend.6.resolved', 1)
                ->where('weeklyTrend.7.weekStart', '2026-11-09')
                ->where('weeklyTrend.7.resolved', 1)
                // Cả 2 lần đều đo từ created_at gốc: (2h + 7 ngày) / 2.
                ->where('avgResolutionSeconds', fn ($value) => (float) $value === (7200.0 + 604800.0) / 2),
            );
    }

    public function test_time_metrics_are_null_and_weeks_are_zero_filled_when_there_is_no_data(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Feedback::factory()->create(['status' => FeedbackStatus::Closed]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('avgFirstResponseSeconds', null)
                ->where('avgResolutionSeconds', null)
                ->has('weeklyTrend', 8)
                ->where('weeklyTrend.0.received', 0)
                ->where('weeklyTrend.7.received', 1)
                ->where('weeklyTrend.7.resolved', 0),
            );
    }

    public function test_admin_can_switch_the_week_range_and_unknown_values_fall_back_to_eight(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('dashboard', ['weeks' => 4]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('weeks', 4)
                ->has('weeklyTrend', 4),
            );

        $this->actingAs($admin)
            ->get(route('dashboard', ['weeks' => 5]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('weeks', 8)
                ->has('weeklyTrend', 8),
            );
    }

    public function test_avg_first_response_ignores_feedbacks_without_a_response(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->travelTo(Carbon::parse('2026-11-10 08:00:00'));
        Feedback::factory()->create(['first_response_at' => Carbon::parse('2026-11-10 09:00:00')]);
        Feedback::factory()->create(['first_response_at' => Carbon::parse('2026-11-10 11:00:00')]);
        Feedback::factory()->create();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('avgFirstResponseSeconds', fn ($value) => (float) $value === 7200.0),
            );
    }

    private function logResolved(Feedback $feedback, User $actor, CarbonInterface $at): void
    {
        FeedbackActivity::factory()->create([
            'feedback_id' => $feedback->id,
            'actor_id' => $actor->id,
            'type' => ActivityType::StatusChange,
            'from_value' => FeedbackStatus::InProgress->value,
            'to_value' => FeedbackStatus::Resolved->value,
            'created_at' => $at,
        ]);
    }
}
