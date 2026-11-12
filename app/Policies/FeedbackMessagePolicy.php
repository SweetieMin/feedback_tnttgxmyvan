<?php

namespace App\Policies;

use App\Enums\MessageVisibility;
use App\Enums\UserRole;
use App\Models\Feedback;
use App\Models\User;

class FeedbackMessagePolicy
{
    public function create(User $user, Feedback $feedback, MessageVisibility $visibility = MessageVisibility::Public): bool
    {
        if (! (new FeedbackPolicy)->view($user, $feedback)) {
            return false;
        }

        return $visibility === MessageVisibility::Public || $user->role !== UserRole::Employee;
    }

    /**
     * Ghi chú nội bộ chỉ hiển thị cho handler/admin, không bao giờ cho nhân viên (A4).
     */
    public function viewInternal(User $user): bool
    {
        return $user->role !== UserRole::Employee;
    }
}
