<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnsureUserRoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'role:handler,admin'])
            ->get('/__test/handler-only', fn () => 'ok');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/__test/handler-only')->assertRedirect();
    }

    public function test_employee_is_forbidden(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee]);

        $this->actingAs($employee)
            ->get('/__test/handler-only')
            ->assertForbidden();
    }

    public function test_handler_is_allowed(): void
    {
        $handler = User::factory()->create(['role' => UserRole::Handler]);

        $this->actingAs($handler)
            ->get('/__test/handler-only')
            ->assertOk();
    }

    public function test_admin_is_allowed(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get('/__test/handler-only')
            ->assertOk();
    }
}
