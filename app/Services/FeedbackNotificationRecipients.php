<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Feedback;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Tính danh sách người nhận thông báo (push) cho một góp ý: chủ góp ý +
 * người được giao + toàn bộ admin, luôn loại trừ chính người vừa thực hiện
 * hành động (không tự thông báo cho mình).
 */
class FeedbackNotificationRecipients
{
    /**
     * @return Collection<int, User>
     */
    public function for(Feedback $feedback, User $actor, bool $includeOwner = true): Collection
    {
        $ids = [];

        if ($includeOwner && $feedback->user_id !== $actor->id) {
            $ids[] = $feedback->user_id;
        }

        if ($feedback->assigned_to !== null && $feedback->assigned_to !== $actor->id) {
            $ids[] = $feedback->assigned_to;
        }

        $adminIds = User::query()
            ->where('role', UserRole::Admin->value)
            ->where('id', '!=', $actor->id)
            ->pluck('id')
            ->all();

        $ids = array_unique(array_merge($ids, $adminIds));

        if ($ids === []) {
            return new Collection;
        }

        return User::query()->whereIn('id', $ids)->get();
    }
}
