<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InitialPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_login_requires_a_different_password_before_accessing_feedback(): void
    {
        $user = User::factory()->unverified()->create(['must_change_password' => true]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->get(route('feedback.index'))->assertRedirect(route('password.first.edit'));
        $this->get(route('password.first.edit'))
            ->assertInertia(fn (Assert $page) => $page->component('auth/initial-password'));

        $this->from(route('password.first.edit'))->put(route('password.first.update'), [
            'current_password' => 'password',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');
        $this->assertTrue($user->refresh()->must_change_password);

        $this->put(route('password.first.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));

        $this->assertFalse($user->refresh()->must_change_password);
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->get(route('feedback.index'))->assertRedirect(route('verification.notice'));
    }

    public function test_a_session_authenticated_with_the_temporary_password_cannot_continue_after_rotation(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);
        $this->actingAs($user)->get(route('password.first.edit'))->assertOk();

        $user->forceFill(['password' => 'new-password', 'must_change_password' => false])->save();
        $this->actingAs($user->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
