<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserAvatarRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Requests\Admin\UpdateUserRoleRequest;
use App\Models\User;
use App\Services\AdminImage;
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
            ->select(['id', 'name', 'email', 'role', 'avatar_path'])
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

    public function store(StoreUserRequest $request, AdminImage $images): RedirectResponse
    {
        $images->save(new User, 'avatars', $request->file('avatar'), function (User $user) use ($request) {
            $user->fill($request->safe()->only(['name', 'email', 'password', 'role']));
            $user->must_change_password = true;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Đã thêm người dùng.']);

        return to_route('admin.users.index');
    }

    public function updateDetails(UpdateUserRequest $request, User $user, AdminImage $images): RedirectResponse
    {
        $images->save($user, 'avatars', $request->file('avatar'), function (User $target) use ($request) {
            $target->fill($request->safe()->only(['name', 'email', 'role']));

            if ($target->isDirty('email')) {
                $target->email_verified_at = null;
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Đã cập nhật người dùng.']);

        return back();
    }

    public function updateAvatar(UpdateUserAvatarRequest $request, User $user, AdminImage $images): RedirectResponse
    {
        $images->save($user, 'avatars', $request->file('avatar'), static fn () => null);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Đã cập nhật ảnh đại diện.']);

        return back();
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
