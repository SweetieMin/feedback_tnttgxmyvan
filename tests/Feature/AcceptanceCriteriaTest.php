<?php

namespace Tests\Feature;

use App\Enums\FeedbackStatus;
use App\Enums\MessageVisibility;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Feedback;
use App\Models\FeedbackMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Mỗi test tương ứng một dòng trong mục 6.2 "Tiêu chí nghiệm thu" của
 * docs/design/feedback-mvp-technical-design.md, chạy trọn qua HTTP như người
 * dùng thật (kể cả gọi thẳng route, bỏ qua giao diện).
 */
class AcceptanceCriteriaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<array<string, mixed>>  $marks
     * @return array<string, mixed>
     */
    private function doc(string $text, array $marks = []): array
    {
        $node = ['type' => 'text', 'text' => $text];

        if ($marks !== []) {
            $node['marks'] = $marks;
        }

        return ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [$node]]]];
    }

    public function test_01_valid_submission_appears_in_my_list_as_new(): void
    {
        $employee = User::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($employee)->post(route('feedback.store'), [
            'title' => 'Máy lạnh phòng họp không mát',
            'content' => $this->doc('Chạy cả buổi vẫn nóng.'),
            'category_id' => $category->id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($employee)
            ->get(route('feedback.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('feedbacks', 1)
                ->where('feedbacks.0.title', 'Máy lạnh phòng họp không mát')
                ->where('feedbacks.0.status', 'new'),
            );
    }

    public function test_02_disallowed_nodes_and_javascript_links_are_rejected_and_not_saved(): void
    {
        $employee = User::factory()->create();
        $category = Category::factory()->create();

        $table = ['type' => 'doc', 'content' => [['type' => 'table', 'content' => []]]];
        $image = ['type' => 'doc', 'content' => [['type' => 'image', 'attrs' => ['src' => 'https://example.com/a.png']]]];
        $script = $this->doc('bấm vào đây', [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]);

        foreach ([$table, $image, $script] as $content) {
            $this->actingAs($employee)
                ->post(route('feedback.store'), [
                    'title' => 'Góp ý',
                    'content' => $content,
                    'category_id' => $category->id,
                ])
                ->assertSessionHasErrors('content');
        }

        $this->assertDatabaseCount('feedbacks', 0);
    }

    public function test_03_second_handler_claiming_the_same_feedback_gets_a_conflict(): void
    {
        $handlerA = User::factory()->create(['role' => UserRole::Handler]);
        $handlerB = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create();

        $this->actingAs($handlerA)
            ->post(route('feedback.self-assign', $feedback))
            ->assertSessionHasNoErrors();

        $feedback->refresh();
        $this->assertSame(FeedbackStatus::Reviewing, $feedback->status);
        $this->assertSame($handlerA->id, $feedback->assigned_to);
        $activityCount = $feedback->activities()->count();

        $this->actingAs($handlerB)
            ->post(route('feedback.self-assign', $feedback))
            ->assertSessionHasErrors('conflict');

        $feedback->refresh();
        $this->assertSame($handlerA->id, $feedback->assigned_to);
        $this->assertSame(FeedbackStatus::Reviewing, $feedback->status);
        $this->assertSame($activityCount, $feedback->activities()->count());
    }

    public function test_04_transitions_follow_table_3_1_and_skipping_steps_is_refused(): void
    {
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $feedback = Feedback::factory()->create();

        // new → resolved gọi thẳng route, bỏ qua giao diện: bị từ chối, DB không đổi.
        $this->actingAs($admin)
            ->patch(route('feedback.status.update', $feedback), ['to_status' => 'resolved'])
            ->assertSessionHasErrors('conflict');
        $this->assertSame(FeedbackStatus::New, $feedback->refresh()->status);

        // Luồng đúng: new → reviewing → in_progress → resolved.
        $this->actingAs($handler)->post(route('feedback.self-assign', $feedback));
        $this->actingAs($handler)->patch(route('feedback.status.update', $feedback), ['to_status' => 'in_progress']);
        $this->actingAs($handler)->post(route('feedback.messages.store', $feedback), ['body' => $this->doc('Đã xử lý xong.')]);
        $this->actingAs($handler)
            ->patch(route('feedback.status.update', $feedback), ['to_status' => 'resolved'])
            ->assertSessionHasNoErrors();
        $this->assertSame(FeedbackStatus::Resolved, $feedback->refresh()->status);

        // resolved mở lại → in_progress; closed mở lại (chỉ admin) → reviewing.
        $this->actingAs($admin)->post(route('feedback.reopen', $feedback), ['reason' => 'Chưa ổn']);
        $this->assertSame(FeedbackStatus::InProgress, $feedback->refresh()->status);

        $closed = Feedback::factory()->create(['status' => FeedbackStatus::Closed, 'closed_reason' => 'Nhầm']);
        $this->actingAs($admin)->post(route('feedback.reopen', $closed), ['reason' => 'Đóng nhầm']);
        $this->assertSame(FeedbackStatus::Reviewing, $closed->refresh()->status);
    }

    public function test_05_closing_requires_a_reason_and_is_recorded_in_history(): void
    {
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create(['status' => FeedbackStatus::Reviewing, 'assigned_to' => $handler->id]);

        $this->actingAs($handler)
            ->patch(route('feedback.status.update', $feedback), ['to_status' => 'closed'])
            ->assertSessionHasErrors('reason');
        $this->assertSame(FeedbackStatus::Reviewing, $feedback->refresh()->status);

        $this->actingAs($handler)
            ->patch(route('feedback.status.update', $feedback), ['to_status' => 'closed', 'reason' => 'Trùng lặp'])
            ->assertSessionHasNoErrors();

        $feedback->refresh();
        $this->assertSame(FeedbackStatus::Closed, $feedback->status);
        $this->assertNotNull($feedback->closed_at);

        $this->actingAs($handler)
            ->get(route('feedback.show', $feedback))
            ->assertInertia(fn (Assert $page) => $page
                ->where('feedback.activities.0.to_value', 'closed')
                ->where('feedback.activities.0.note', 'Trùng lặp'),
            );
    }

    public function test_06_internal_note_never_reaches_the_owner(): void
    {
        $owner = User::factory()->create();
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create(['user_id' => $owner->id, 'status' => FeedbackStatus::Reviewing, 'assigned_to' => $handler->id]);
        $secret = 'Bí mật nội bộ 7f3a';

        $this->actingAs($handler)->post(route('feedback.messages.store', $feedback), [
            'body' => $this->doc($secret),
            'visibility' => MessageVisibility::Internal->value,
        ])->assertSessionHasNoErrors();

        $this->actingAs($owner)
            ->get(route('feedback.show', $feedback))
            ->assertOk()
            ->assertDontSee($secret)
            ->assertInertia(fn (Assert $page) => $page->has('feedback.messages', 0));

        $this->actingAs($owner)
            ->get(route('notifications.index'))
            ->assertDontSee($secret)
            ->assertInertia(fn (Assert $page) => $page->has('notifications', 0));
    }

    public function test_07_employee_opening_another_employees_feedback_gets_403(): void
    {
        $feedback = Feedback::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('feedback.show', $feedback))
            ->assertForbidden();
    }

    public function test_08_only_admin_can_reassign_and_the_new_handler_is_notified(): void
    {
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $other = User::factory()->create(['role' => UserRole::Handler]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $feedback = Feedback::factory()->create(['status' => FeedbackStatus::Reviewing, 'assigned_to' => $other->id]);

        $this->actingAs($handler)
            ->patch(route('feedback.reassign', $feedback), ['assigned_to' => $handler->id])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('feedback.reassign', $feedback), ['assigned_to' => $handler->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($handler->id, $feedback->refresh()->assigned_to);
        $this->assertDatabaseHas('feedback_notifications', [
            'user_id' => $handler->id,
            'feedback_id' => $feedback->id,
            'type' => 'assigned',
        ]);
    }

    public function test_09_admin_new_count_matches_the_filtered_admin_list(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Feedback::factory()->count(3)->create();
        Feedback::factory()->create(['status' => FeedbackStatus::Reviewing]);

        $dashboardCount = $this->actingAs($admin)->get(route('dashboard'))->inertiaProps('statusCounts.new');

        $this->actingAs($admin)
            ->get(route('admin.feedback.index', ['status' => 'new']))
            ->assertInertia(fn (Assert $page) => $page->has('feedbacks', $dashboardCount));

        $this->assertSame(3, $dashboardCount);
    }

    public function test_10_resolution_time_shows_no_data_when_nothing_was_resolved(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Feedback::factory()->count(2)->create();
        Feedback::factory()->create(['status' => FeedbackStatus::Closed]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('avgResolutionSeconds', null));
    }

    public function test_11_reopened_then_resolved_again_counts_in_both_weeks(): void
    {
        $owner = User::factory()->create();
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->travelTo(Carbon::parse('2026-11-04 09:00:00'));
        $feedback = Feedback::factory()->create(['user_id' => $owner->id, 'status' => FeedbackStatus::InProgress, 'assigned_to' => $handler->id]);
        FeedbackMessage::factory()->create(['feedback_id' => $feedback->id, 'author_id' => $handler->id]);
        $this->actingAs($handler)->patch(route('feedback.status.update', $feedback), ['to_status' => 'resolved']);

        $this->travelTo(Carbon::parse('2026-11-11 09:00:00'));
        $this->actingAs($owner)->post(route('feedback.reopen', $feedback), ['reason' => 'Vẫn lỗi']);
        $this->actingAs($handler)->patch(route('feedback.status.update', $feedback), ['to_status' => 'resolved']);

        $this->travelTo(Carbon::parse('2026-11-12 09:00:00'));

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('weeklyTrend.6.weekStart', '2026-11-02')
                ->where('weeklyTrend.6.resolved', 1)
                ->where('weeklyTrend.7.weekStart', '2026-11-09')
                ->where('weeklyTrend.7.resolved', 1),
            );
    }
}
