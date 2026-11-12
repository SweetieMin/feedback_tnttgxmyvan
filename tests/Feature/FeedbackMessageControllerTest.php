<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Feedback;
use App\Models\User;
use App\Notifications\FeedbackPushNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FeedbackMessageControllerTest extends TestCase
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

    public function test_owner_can_post_a_public_reply_without_self_notification(): void
    {
        $owner = User::factory()->create();
        $feedback = Feedback::factory()->create(['user_id' => $owner->id]);
        $body = $this->paragraphDoc('Bổ sung thêm thông tin.');
        $body['content'][] = [
            'type' => 'image',
            'attrs' => [
                'src' => '/storage/feedback-images/'.str_repeat('a', 40).'.png',
                'width' => 320,
                'height' => null,
                'data-align' => 'center',
            ],
        ];

        $this->actingAs($owner)
            ->post(route('feedback.messages.store', $feedback), [
                'body' => $body,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('feedback_messages', [
            'feedback_id' => $feedback->id,
            'author_id' => $owner->id,
            'visibility' => 'public',
        ]);
        $this->assertSame($body, $feedback->messages()->sole()->body);
        $this->assertDatabaseMissing('feedback_notifications', ['feedback_id' => $feedback->id]);
        $this->assertNull($feedback->refresh()->first_response_at);
    }

    public function test_handler_public_reply_sets_first_response_at_and_notifies_owner(): void
    {
        $owner = User::factory()->create();
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create(['user_id' => $owner->id, 'assigned_to' => $handler->id]);

        $this->actingAs($handler)
            ->post(route('feedback.messages.store', $feedback), [
                'body' => $this->paragraphDoc('Chúng tôi đang xem xét.'),
                'visibility' => 'public',
            ])
            ->assertSessionHasNoErrors();

        $feedback->refresh();

        $this->assertNotNull($feedback->first_response_at);
        $this->assertDatabaseHas('feedback_notifications', [
            'user_id' => $owner->id,
            'feedback_id' => $feedback->id,
            'type' => 'new_reply',
        ]);
    }

    public function test_public_reply_pushes_owner_and_admin_but_not_the_author(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $feedback = Feedback::factory()->create(['user_id' => $owner->id, 'assigned_to' => $handler->id]);

        $this->actingAs($handler)->post(route('feedback.messages.store', $feedback), [
            'body' => $this->paragraphDoc('Chúng tôi đang xem xét.'),
            'visibility' => 'public',
        ]);

        Notification::assertSentTo([$owner, $admin], FeedbackPushNotification::class, fn ($notification) => $notification->feedback->is($feedback) && $notification->event === 'replied');
        Notification::assertNotSentTo($handler, FeedbackPushNotification::class);
    }

    public function test_internal_note_pushes_admin_but_never_the_owner(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $feedback = Feedback::factory()->create(['user_id' => $owner->id, 'assigned_to' => $handler->id]);

        $this->actingAs($handler)->post(route('feedback.messages.store', $feedback), [
            'body' => $this->paragraphDoc('Ghi chú riêng cho team.'),
            'visibility' => 'internal',
        ]);

        Notification::assertSentTo($admin, FeedbackPushNotification::class);
        Notification::assertNotSentTo($owner, FeedbackPushNotification::class);
    }

    public function test_second_public_reply_does_not_overwrite_first_response_at(): void
    {
        $owner = User::factory()->create();
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create(['user_id' => $owner->id, 'assigned_to' => $handler->id]);

        $this->actingAs($handler)->post(route('feedback.messages.store', $feedback), [
            'body' => $this->paragraphDoc('Trả lời đầu tiên.'),
        ]);
        $firstResponseAt = $feedback->refresh()->first_response_at;

        $this->travel(1)->hour();

        $this->actingAs($handler)->post(route('feedback.messages.store', $feedback), [
            'body' => $this->paragraphDoc('Trả lời thứ hai.'),
        ]);

        $this->assertTrue($firstResponseAt->equalTo($feedback->refresh()->first_response_at));
    }

    public function test_handler_can_post_internal_note_without_notifying_owner(): void
    {
        $owner = User::factory()->create();
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $feedback = Feedback::factory()->create(['user_id' => $owner->id, 'assigned_to' => $handler->id]);

        $this->actingAs($handler)
            ->post(route('feedback.messages.store', $feedback), [
                'body' => $this->paragraphDoc('Ghi chú riêng cho team.'),
                'visibility' => 'internal',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('feedback_messages', [
            'feedback_id' => $feedback->id,
            'visibility' => 'internal',
        ]);
        $this->assertDatabaseMissing('feedback_notifications', ['feedback_id' => $feedback->id]);
        $this->assertNull($feedback->refresh()->first_response_at);
    }

    public function test_employee_cannot_post_internal_note(): void
    {
        $owner = User::factory()->create();
        $feedback = Feedback::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->post(route('feedback.messages.store', $feedback), [
                'body' => $this->paragraphDoc('Thử ghi nội bộ.'),
                'visibility' => 'internal',
            ])
            ->assertForbidden();
    }

    public function test_employee_cannot_post_message_on_another_employees_feedback(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $feedback = Feedback::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other)
            ->post(route('feedback.messages.store', $feedback), [
                'body' => $this->paragraphDoc('Không liên quan.'),
            ])
            ->assertForbidden();
    }

    public function test_message_rejects_disallowed_tiptap_node(): void
    {
        $owner = User::factory()->create();
        $feedback = Feedback::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->post(route('feedback.messages.store', $feedback), [
                'body' => [
                    'type' => 'doc',
                    'content' => [
                        ['type' => 'table', 'content' => []],
                    ],
                ],
            ])
            ->assertSessionHasErrors('body');

        $this->assertDatabaseCount('feedback_messages', 0);
    }
}
