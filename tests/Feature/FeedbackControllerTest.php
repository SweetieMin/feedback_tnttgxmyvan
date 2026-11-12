<?php

namespace Tests\Feature;

use App\Enums\FeedbackStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Feedback;
use App\Models\FeedbackMessage;
use App\Models\User;
use App\Notifications\FeedbackPushNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FeedbackControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function paragraphDoc(string $text): array
    {
        return [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => $text],
                    ],
                ],
            ],
        ];
    }

    public function test_guest_is_redirected_to_login_for_every_feedback_route(): void
    {
        $feedback = Feedback::factory()->create();

        $this->get(route('feedback.index'))->assertRedirect(route('login'));
        $this->get(route('feedback.create'))->assertRedirect(route('login'));
        $this->get(route('feedback.show', $feedback))->assertRedirect(route('login'));
        $this->post(route('feedback.store'))->assertRedirect(route('login'));
        $this->patch(route('feedback.update', $feedback))->assertRedirect(route('login'));
    }

    public function test_employee_can_submit_feedback(): void
    {
        $employee = User::factory()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($employee)->post(route('feedback.store'), [
            'title' => 'Góp ý về quy trình',
            'content' => $this->paragraphDoc('Nội dung góp ý chi tiết.'),
            'category_id' => $category->id,
        ]);

        $response->assertSessionHasNoErrors();

        $feedback = Feedback::where('user_id', $employee->id)->firstOrFail();

        $response->assertRedirect(route('feedback.show', $feedback));
        $this->assertSame(FeedbackStatus::New, $feedback->status);
        $this->assertSame('Nội dung góp ý chi tiết.', $feedback->excerpt);
        $this->assertDatabaseHas('feedback_activities', [
            'feedback_id' => $feedback->id,
            'actor_id' => $employee->id,
            'to_value' => 'new',
        ]);
    }

    public function test_submitting_feedback_pushes_admins_but_not_the_submitter(): void
    {
        Notification::fake();

        $employee = User::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $category = Category::factory()->create();

        $this->actingAs($employee)->post(route('feedback.store'), [
            'title' => 'Góp ý về quy trình',
            'content' => $this->paragraphDoc('Nội dung góp ý chi tiết.'),
            'category_id' => $category->id,
        ]);

        $feedback = Feedback::where('user_id', $employee->id)->firstOrFail();

        Notification::assertSentTo($admin, FeedbackPushNotification::class, fn ($notification) => $notification->feedback->is($feedback) && $notification->event === 'created');
        Notification::assertNotSentTo($employee, FeedbackPushNotification::class);
    }

    public function test_store_rejects_disallowed_tiptap_node(): void
    {
        $employee = User::factory()->create();
        $category = Category::factory()->create();

        $this->actingAs($employee)
            ->from(route('feedback.create'))
            ->post(route('feedback.store'), [
                'title' => 'Góp ý',
                'content' => ['type' => 'doc', 'content' => [['type' => 'table', 'content' => []]]],
                'category_id' => $category->id,
            ])
            ->assertSessionHasErrors('content');

        $this->assertDatabaseCount('feedbacks', 0);
    }

    public function test_store_rejects_inactive_category(): void
    {
        $employee = User::factory()->create();
        $category = Category::factory()->inactive()->create();

        $this->actingAs($employee)
            ->post(route('feedback.store'), [
                'title' => 'Góp ý',
                'content' => $this->paragraphDoc('Nội dung.'),
                'category_id' => $category->id,
            ])
            ->assertSessionHasErrors('category_id');
    }

    public function test_employee_cannot_view_another_employees_feedback_directly(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $feedback = Feedback::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other)
            ->get(route('feedback.show', $feedback))
            ->assertForbidden();
    }

    public function test_owner_can_update_feedback_while_still_new(): void
    {
        $owner = User::factory()->create();
        $feedback = Feedback::factory()->create(['user_id' => $owner->id, 'status' => FeedbackStatus::New]);
        $newCategory = Category::factory()->create();

        $this->actingAs($owner)
            ->patch(route('feedback.update', $feedback), [
                'title' => 'Tiêu đề mới',
                'content' => $this->paragraphDoc('Nội dung đã sửa.'),
                'category_id' => $newCategory->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('feedback.show', $feedback));

        $feedback->refresh();

        $this->assertSame('Tiêu đề mới', $feedback->title);
        $this->assertSame('Nội dung đã sửa.', $feedback->excerpt);
        $this->assertSame($newCategory->id, $feedback->category_id);
    }

    public function test_owner_cannot_update_feedback_once_it_left_new_status(): void
    {
        $owner = User::factory()->create();
        $feedback = Feedback::factory()->create(['user_id' => $owner->id, 'status' => FeedbackStatus::Reviewing]);

        $this->actingAs($owner)
            ->patch(route('feedback.update', $feedback), ['title' => 'Không được sửa'])
            ->assertForbidden();

        $this->assertNotSame('Không được sửa', $feedback->refresh()->title);
    }

    public function test_other_employee_cannot_update_feedback(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $feedback = Feedback::factory()->create(['user_id' => $owner->id, 'status' => FeedbackStatus::New]);

        $this->actingAs($other)
            ->patch(route('feedback.update', $feedback), ['title' => 'Không được phép'])
            ->assertForbidden();
    }

    public function test_store_validation_messages_are_in_vietnamese(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee)
            ->post(route('feedback.store'), [])
            ->assertSessionHasErrors([
                'title' => 'Vui lòng nhập tiêu đề.',
                'content' => 'Vui lòng nhập nội dung góp ý.',
                'category_id' => 'Vui lòng chọn danh mục.',
            ]);
    }

    public function test_index_lists_only_own_feedbacks_and_applies_status_filter(): void
    {
        $owner = User::factory()->create();
        Feedback::factory()->create(['user_id' => $owner->id]);
        $resolved = Feedback::factory()->create(['user_id' => $owner->id, 'status' => FeedbackStatus::Resolved]);
        Feedback::factory()->create(['status' => FeedbackStatus::Resolved]);

        $this->actingAs($owner)
            ->get(route('feedback.index', ['status' => 'resolved']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('feedback/index')
                ->has('feedbacks', 1)
                ->where('feedbacks.0.id', $resolved->id)
                ->where('filters.status', 'resolved')
                ->has('categories'),
            );
    }

    public function test_create_page_only_offers_active_categories(): void
    {
        $employee = User::factory()->create();
        $active = Category::factory()->create();
        Category::factory()->inactive()->create();

        $this->actingAs($employee)
            ->get(route('feedback.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('feedback/create')
                ->has('categories', 1)
                ->where('categories.0.id', $active->id),
            );
    }

    public function test_owner_sees_feedback_without_internal_notes_and_can_edit_while_new(): void
    {
        $owner = User::factory()->create();
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create(['user_id' => $owner->id, 'assigned_to' => $handler->id]);

        FeedbackMessage::factory()->create([
            'feedback_id' => $feedback->id,
            'author_id' => $handler->id,
            'body' => $this->paragraphDoc('Trả lời công khai cho nhân viên'),
        ]);
        FeedbackMessage::factory()->internal()->create([
            'feedback_id' => $feedback->id,
            'author_id' => $handler->id,
            'body' => $this->paragraphDoc('Bí mật nội bộ của đội xử lý'),
        ]);

        $this->actingAs($owner)
            ->get(route('feedback.show', $feedback))
            ->assertOk()
            ->assertDontSee('Bí mật nội bộ của đội xử lý')
            ->assertInertia(fn (Assert $page) => $page
                ->component('feedback/show')
                ->has('feedback.messages', 1)
                ->where('feedback.messages.0.visibility', 'public')
                ->where('can.update', true)
                ->where('can.reopen', false)
                ->has('categories'),
            );
    }

    public function test_assigned_handler_gets_status_actions_and_sees_internal_notes(): void
    {
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create(['status' => FeedbackStatus::InProgress, 'assigned_to' => $handler->id]);
        FeedbackMessage::factory()->internal()->create(['feedback_id' => $feedback->id, 'author_id' => $handler->id]);

        $this->actingAs($handler)
            ->get(route('feedback.show', $feedback))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.changeStatus', true)
                ->where('can.selfAssign', false)
                ->where('can.reassign', false)
                ->where('can.addInternalNote', true)
                ->where('transitions', ['resolved', 'closed'])
                ->where('handlers', [])
                ->has('feedback.messages', 1)
                ->has('feedback.sender'),
            );
    }

    public function test_unassigned_handler_can_only_self_assign_a_new_feedback(): void
    {
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create();

        $this->actingAs($handler)
            ->get(route('feedback.show', $feedback))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.selfAssign', true)
                ->where('can.changeStatus', false)
                ->where('transitions', []),
            );
    }

    public function test_admin_gets_reassign_with_handler_list(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        User::factory()->count(2)->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create(['status' => FeedbackStatus::Reviewing]);

        $this->actingAs($admin)
            ->get(route('feedback.show', $feedback))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.reassign', true)
                ->where('can.selfAssign', false)
                ->where('transitions', ['in_progress', 'closed'])
                ->has('handlers', 2),
            );
    }

    public function test_employee_owner_gets_no_handler_actions(): void
    {
        $owner = User::factory()->create();
        $feedback = Feedback::factory()->create(['user_id' => $owner->id, 'status' => FeedbackStatus::Reviewing]);

        $this->actingAs($owner)
            ->get(route('feedback.show', $feedback))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.selfAssign', false)
                ->where('can.changeStatus', false)
                ->where('can.reassign', false)
                ->where('can.addInternalNote', false)
                ->where('transitions', []),
            );
    }

    public function test_owner_can_reopen_but_not_edit_a_resolved_feedback(): void
    {
        $owner = User::factory()->create();
        $feedback = Feedback::factory()->create(['user_id' => $owner->id, 'status' => FeedbackStatus::Resolved]);

        $this->actingAs($owner)
            ->get(route('feedback.show', $feedback))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.update', false)
                ->where('can.reopen', true)
                ->where('categories', []),
            );
    }
}
