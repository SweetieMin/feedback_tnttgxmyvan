<?php

namespace Tests\Feature;

use App\Models\FeedbackNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_only_own_notifications_newest_first(): void
    {
        $user = User::factory()->create();
        $older = FeedbackNotification::factory()->for($user)->create(['created_at' => now()->subHour()]);
        $newer = FeedbackNotification::factory()->for($user)->create();
        FeedbackNotification::factory()->create();

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('notifications/index')
                ->has('notifications', 2)
                ->where('notifications.0.id', $newer->id)
                ->where('notifications.1.id', $older->id)
                ->has('notifications.0.feedback.title'),
            );
    }

    public function test_unread_count_only_counts_current_users_unread_notifications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        FeedbackNotification::factory()->for($user)->count(2)->create();
        FeedbackNotification::factory()->for($user)->create(['read_at' => now()]);
        FeedbackNotification::factory()->for($other)->create();

        $response = $this->actingAs($user)->getJson(route('notifications.unread-count'));

        $response->assertOk()->assertJson(['count' => 2]);
    }

    public function test_owner_can_mark_own_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = FeedbackNotification::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('notifications.read', $notification))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($notification->refresh()->read_at);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $notification = FeedbackNotification::factory()->for($owner)->create();

        $this->actingAs($other)
            ->post(route('notifications.read', $notification))
            ->assertForbidden();

        $this->assertNull($notification->refresh()->read_at);
    }

    public function test_marking_already_read_notification_again_keeps_original_timestamp(): void
    {
        $user = User::factory()->create();
        $notification = FeedbackNotification::factory()->for($user)->create(['read_at' => now()->subDay()]);
        $originalReadAt = $notification->read_at;

        $this->actingAs($user)->post(route('notifications.read', $notification));

        $this->assertTrue($originalReadAt->equalTo($notification->refresh()->read_at));
    }

    public function test_guest_cannot_access_notification_endpoints(): void
    {
        $notification = FeedbackNotification::factory()->create();

        $this->getJson(route('notifications.unread-count'))->assertUnauthorized();
        $this->post(route('notifications.read', $notification))->assertRedirect(route('login'));
    }
}
