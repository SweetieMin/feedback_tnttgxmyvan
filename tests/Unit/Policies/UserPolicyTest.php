<?php

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_update_user_role(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $employee = User::factory()->create(['role' => UserRole::Employee]);
        $target = User::factory()->create(['role' => UserRole::Employee]);

        $this->assertTrue($admin->can('updateRole', $target));
        $this->assertFalse($handler->can('updateRole', $target));
        $this->assertFalse($employee->can('updateRole', $target));
    }
}
