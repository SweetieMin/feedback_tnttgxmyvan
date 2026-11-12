<?php

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_manage_categories(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $handler = User::factory()->create(['role' => UserRole::Handler]);
        $employee = User::factory()->create(['role' => UserRole::Employee]);

        $this->assertTrue($admin->can('create', Category::class));
        $this->assertFalse($handler->can('create', Category::class));
        $this->assertFalse($employee->can('create', Category::class));

        $category = Category::factory()->create();

        $this->assertTrue($admin->can('update', $category));
        $this->assertFalse($handler->can('update', $category));
        $this->assertFalse($employee->can('update', $category));
    }
}
