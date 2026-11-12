<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    /**
     * Mọi hành động quản lý danh mục chỉ dành cho quản trị viên (A1).
     *
     * Laravel chỉ gọi before() khi ability tương ứng tồn tại dưới dạng phương
     * thức trên policy, nên các phương thức bên dưới luôn phải được khai báo
     * (giá trị trả về false chỉ là mặc định khi before() không chặn trước).
     */
    public function before(User $user, string $ability): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Category $category): bool
    {
        return false;
    }

    public function delete(User $user, Category $category): bool
    {
        return false;
    }
}
