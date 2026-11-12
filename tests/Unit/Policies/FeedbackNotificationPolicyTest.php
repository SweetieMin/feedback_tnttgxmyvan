<?php

namespace Tests\Unit\Policies;

use App\Models\FeedbackNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackNotificationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_owner_can_mark_read(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $notification = FeedbackNotification::factory()->for($owner)->create();

        $this->assertTrue($owner->can('markRead', $notification));
        $this->assertFalse($other->can('markRead', $notification));
    }
}
