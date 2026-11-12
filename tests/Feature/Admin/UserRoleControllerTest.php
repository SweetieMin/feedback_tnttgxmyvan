<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserRoleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_users_by_name_or_email(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'name' => 'Quản trị']);
        User::factory()->create(['name' => 'Nguyễn Văn An', 'email' => 'an@example.com']);
        User::factory()->create(['name' => 'Trần Bình', 'email' => 'binh@example.com']);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users')
                ->has('users', 3),
            );

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['search' => 'binh@']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('users', 1)
                ->where('users.0.name', 'Trần Bình')
                ->where('filters.search', 'binh@'),
            );
    }

    public function test_admin_cannot_change_their_own_role(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->patch(route('admin.users.role', $admin), ['role' => 'employee'])
            ->assertSessionHasErrors(['role' => 'Bạn không thể tự đổi vai trò của chính mình.']);

        $this->assertSame(UserRole::Admin, $admin->refresh()->role);
    }

    public function test_employee_and_handler_cannot_view_users_index(): void
    {
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $handler = User::factory()->create(['role' => UserRole::Handler]);

        $this->actingAs($employee)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($handler)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_admin_can_change_a_users_role(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $target = User::factory()->create(['role' => UserRole::Employee]);

        $listUrl = route('admin.users.index', ['search' => 'abc']);

        $this->actingAs($admin)
            ->from($listUrl)
            ->patch(route('admin.users.role', $target), ['role' => 'handler'])
            ->assertSessionHasNoErrors()
            ->assertRedirect($listUrl);

        $this->assertSame(UserRole::Handler, $target->refresh()->role);
    }

    public function test_role_must_be_a_valid_enum_value(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $target = User::factory()->create(['role' => UserRole::Employee]);

        $this->actingAs($admin)
            ->patch(route('admin.users.role', $target), ['role' => 'super-admin'])
            ->assertSessionHasErrors('role');

        $this->assertSame(UserRole::Employee, $target->refresh()->role);
    }

    public function test_non_admin_cannot_change_a_users_role(): void
    {
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $target = User::factory()->create(['role' => UserRole::Employee]);

        $this->actingAs($handler)
            ->patch(route('admin.users.role', $target), ['role' => 'admin'])
            ->assertForbidden();

        $this->assertSame(UserRole::Employee, $target->refresh()->role);
    }
}
