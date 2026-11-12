<?php

namespace App\Policies;

use App\Models\FeedbackNotification;
use App\Models\User;

class FeedbackNotificationPolicy
{
    /**
     * Chỉ chủ sở hữu được đánh dấu đã đọc thông báo của chính mình (mục 1.3).
     */
    public function markRead(User $user, FeedbackNotification $notification): bool
    {
        return $user->id === $notification->user_id;
    }
}
