<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    /**
     * Chỉ quản trị viên được quản lý vai trò người dùng (mục 4.2).
     */
    public function before(User $user, string $ability): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function updateRole(User $user, User $target): bool
    {
        return false;
    }
}
