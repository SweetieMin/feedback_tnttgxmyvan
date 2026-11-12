<?php

namespace Tests\Unit\Models;

use App\Models\FeedbackNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserFeedbackNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_feedback_notifications_relation_only_returns_own_notifications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        FeedbackNotification::factory()->for($user)->count(2)->create();
        FeedbackNotification::factory()->for($other)->create();

        $this->assertCount(2, $user->feedbackNotifications()->get());
    }

    public function test_feedback_notifications_relation_is_separate_from_notifiable_trait(): void
    {
        $user = User::factory()->create();

        // Khẳng định không bị trùng/đè với notifications() của trait Notifiable (A6).
        $this->assertNotSame($user->notifications(), $user->feedbackNotifications());
    }
}
