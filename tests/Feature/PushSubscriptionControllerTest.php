<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushSubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(string $endpoint): array
    {
        return [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => 'public-key-value',
                'auth' => 'auth-token-value',
            ],
        ];
    }

    public function test_user_can_subscribe(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('push.subscribe'), $this->payload('https://fcm.googleapis.com/fcm/send/abc'))
            ->assertNoContent();

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
            'public_key' => 'public-key-value',
            'auth_token' => 'auth-token-value',
        ]);
    }

    public function test_resubscribing_same_endpoint_updates_instead_of_duplicating(): void
    {
        $user = User::factory()->create();
        $endpoint = 'https://fcm.googleapis.com/fcm/send/abc';

        $this->actingAs($user)->postJson(route('push.subscribe'), $this->payload($endpoint));
        $this->actingAs($user)->postJson(route('push.subscribe'), [
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => 'rotated-key', 'auth' => 'rotated-token'],
        ]);

        $this->assertSame(1, PushSubscription::where('endpoint', $endpoint)->count());
        $this->assertDatabaseHas('push_subscriptions', [
            'endpoint' => $endpoint,
            'public_key' => 'rotated-key',
        ]);
    }

    public function test_user_can_only_unsubscribe_their_own_subscription(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $subscription = PushSubscription::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($otherUser)
            ->deleteJson(route('push.unsubscribe'), ['endpoint' => $subscription->endpoint])
            ->assertNoContent();

        $this->assertDatabaseHas('push_subscriptions', ['id' => $subscription->id]);

        $this->actingAs($owner)
            ->deleteJson(route('push.unsubscribe'), ['endpoint' => $subscription->endpoint])
            ->assertNoContent();

        $this->assertDatabaseMissing('push_subscriptions', ['id' => $subscription->id]);
    }
}
