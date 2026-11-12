<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRoleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserRoleController extends Controller
{
    /**
     * Danh sách người dùng cho quản trị viên đổi vai trò, lọc theo tên/email.
     */
    public function index(Request $request): Response
    {
        $users = User::query()
            ->select(['id', 'name', 'email', 'role'])
            ->when(
                $request->filled('search'),
                fn ($query) => $query->where(
                    fn ($query) => $query
                        ->where('name', 'like', '%'.$request->string('search').'%')
                        ->orWhere('email', 'like', '%'.$request->string('search').'%')
                ),
            )
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/users', [
            'users' => $users,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Đổi vai trò một người dùng.
     */
    public function update(UpdateUserRoleRequest $request, User $user): RedirectResponse
    {
        $user->update(['role' => $request->role()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Đã cập nhật vai trò.')]);

        return back();
    }
}
