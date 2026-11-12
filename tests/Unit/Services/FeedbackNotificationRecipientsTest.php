<?php

namespace Tests\Unit\Services;

use App\Enums\UserRole;
use App\Models\Feedback;
use App\Models\User;
use App\Services\FeedbackNotificationRecipients;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedbackNotificationRecipientsTest extends TestCase
{
    use RefreshDatabase;

    private FeedbackNotificationRecipients $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new FeedbackNotificationRecipients;
    }

    public function test_includes_owner_assignee_and_admins_excluding_actor(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Employee]);
        $assignee = User::factory()->create(['role' => UserRole::Handler]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $actor = User::factory()->create(['role' => UserRole::Handler]);

        $feedback = Feedback::factory()->create([
            'user_id' => $owner->id,
            'assigned_to' => $assignee->id,
        ]);

        $recipients = $this->service->for($feedback, $actor);

        $this->assertEqualsCanonicalizing(
            [$owner->id, $assignee->id, $admin->id],
            $recipients->pluck('id')->all(),
        );
    }

    public function test_excludes_owner_when_include_owner_is_false(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Employee]);
        $actor = User::factory()->create(['role' => UserRole::Handler]);

        $feedback = Feedback::factory()->create(['user_id' => $owner->id]);

        $recipients = $this->service->for($feedback, $actor, includeOwner: false);

        $this->assertFalse($recipients->contains('id', $owner->id));
    }

    public function test_excludes_actor_even_when_actor_is_owner_assignee_or_admin(): void
    {
        $actor = User::factory()->create(['role' => UserRole::Admin]);
        $feedback = Feedback::factory()->create([
            'user_id' => $actor->id,
            'assigned_to' => $actor->id,
        ]);

        $recipients = $this->service->for($feedback, $actor);

        $this->assertTrue($recipients->isEmpty());
    }

    public function test_dedupes_when_assignee_is_also_an_admin(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Employee]);
        $assigneeAdmin = User::factory()->create(['role' => UserRole::Admin]);
        $actor = User::factory()->create(['role' => UserRole::Handler]);

        $feedback = Feedback::factory()->create([
            'user_id' => $owner->id,
            'assigned_to' => $assigneeAdmin->id,
        ]);

        $recipients = $this->service->for($feedback, $actor);

        $this->assertEqualsCanonicalizing(
            [$owner->id, $assigneeAdmin->id],
            $recipients->pluck('id')->all(),
        );
    }
}
