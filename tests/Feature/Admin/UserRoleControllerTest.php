<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserRoleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_edit_user_details_and_avatar_together(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $user = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.update', $user), [
            '_method' => 'PATCH',
            'name' => 'Tên mới',
            'email' => 'NEW@example.com',
            'role' => 'handler',
            'avatar' => UploadedFile::fake()->image('avatar.png'),
        ])->assertSessionHasNoErrors();

        $this->assertSame('Tên mới', $user->refresh()->name);
        $this->assertSame('new@example.com', $user->email);
        $this->assertSame(UserRole::Handler, $user->role);
        $this->assertNull($user->email_verified_at);
        Storage::disk('public')->assertExists($user->avatar_path);

        $this->patch(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'employee',
        ])->assertSessionHasErrors('role');
        $this->assertSame(UserRole::Admin, $admin->refresh()->role);
    }

    public function test_admin_can_create_a_user_with_an_avatar(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Nguyễn Văn An',
            'email' => 'AN@example.com',
            'password' => 'example-password',
            'password_confirmation' => 'example-password',
            'role' => 'handler',
            'avatar' => UploadedFile::fake()->image('avatar.png'),
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'an@example.com')->sole();
        $this->assertSame(UserRole::Handler, $user->role);
        $this->assertTrue(Hash::check('example-password', $user->password));
        $this->assertTrue($user->must_change_password);
        $this->assertNull($user->email_verified_at);
        Storage::disk('public')->assertExists($user->avatar_path);
        $this->get(route('admin.users.index', ['search' => 'an@example.com']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('users.0.avatar', Storage::disk('public')->url($user->avatar_path))
                ->missing('users.0.avatar_path'));
    }

    public function test_admin_can_replace_an_avatar_and_invalid_upload_keeps_it(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $user = User::factory()->create();
        $oldPath = UploadedFile::fake()->image('old.png')->store('avatars', 'public');
        $user->forceFill(['avatar_path' => $oldPath])->save();

        $this->actingAs($admin)->post(route('admin.users.avatar', $user), [
            'avatar' => UploadedFile::fake()->create('document.pdf'),
        ])->assertSessionHasErrors('avatar');
        $this->assertSame($oldPath, $user->refresh()->avatar_path);
        Storage::disk('public')->assertExists($oldPath);

        $this->actingAs($admin)->post(route('admin.users.avatar', $user), [
            'avatar' => UploadedFile::fake()->image('new.png'),
        ])->assertSessionHasNoErrors();

        $this->assertNotSame($oldPath, $user->refresh()->avatar_path);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($user->avatar_path);
    }

    public function test_non_admin_cannot_create_users_or_upload_their_avatars(): void
    {
        $handler = User::factory()->create(['role' => UserRole::Handler]);

        $this->actingAs($handler)->post(route('admin.users.store'))->assertForbidden();
        $this->actingAs($handler)->post(route('admin.users.avatar', $handler))->assertForbidden();
    }

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
