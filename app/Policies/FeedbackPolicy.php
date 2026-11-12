<?php

namespace App\Policies;

use App\Enums\FeedbackStatus;
use App\Enums\UserRole;
use App\Models\Feedback;
use App\Models\User;

class FeedbackPolicy
{
    /**
     * Mọi người dùng đã đăng nhập đều được gửi góp ý (giả định A2).
     */
    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Feedback $feedback): bool
    {
        return $user->id === $feedback->user_id || $user->role !== UserRole::Employee;
    }

    /**
     * Chỉ chủ sở hữu được sửa nội dung gốc, và chỉ khi còn ở trạng thái "new" (A10).
     */
    public function update(User $user, Feedback $feedback): bool
    {
        return $user->id === $feedback->user_id && $feedback->status === FeedbackStatus::New;
    }

    /**
     * Chỉ góp ý "new" — góp ý đã đóng mà chưa từng có người phụ trách không
     * được "tự nhận" để kéo về reviewing (mở lại closed chỉ dành cho admin, 3.1).
     */
    public function selfAssign(User $user, Feedback $feedback): bool
    {
        return $user->role === UserRole::Handler
            && $feedback->assigned_to === null
            && $feedback->status === FeedbackStatus::New;
    }

    public function changeStatus(User $user, Feedback $feedback): bool
    {
        return $user->role === UserRole::Admin
            || ($user->role === UserRole::Handler && $feedback->assigned_to === $user->id);
    }

    public function reassign(User $user, Feedback $feedback): bool
    {
        return $user->role === UserRole::Admin
            && ! in_array($feedback->status, [FeedbackStatus::Resolved, FeedbackStatus::Closed], true);
    }

    public function reopen(User $user, Feedback $feedback): bool
    {
        if (! in_array($feedback->status, [FeedbackStatus::Resolved, FeedbackStatus::Closed], true)) {
            return false;
        }

        if ($feedback->status === FeedbackStatus::Closed) {
            return $user->role === UserRole::Admin;
        }

        return $user->id === $feedback->user_id
            || $user->role === UserRole::Admin
            || ($user->role === UserRole::Handler && $feedback->assigned_to === $user->id);
    }
}
