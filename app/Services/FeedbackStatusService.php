<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Enums\FeedbackStatus;
use App\Enums\MessageVisibility;
use App\Exceptions\FeedbackConflictException;
use App\Models\Feedback;
use App\Models\FeedbackActivity;
use App\Models\FeedbackNotification;
use App\Models\User;
use App\Notifications\FeedbackPushNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Nơi duy nhất được phép ghi feedbacks.status/assigned_to — mọi controller
 * khác chỉ gọi qua service này để lịch sử (feedback_activities) và trạng
 * thái luôn nhất quán, kể cả khi hai người cùng cập nhật (mục 3.3).
 */
class FeedbackStatusService
{
    /**
     * Các chuyển tiếp "xuôi" hợp lệ qua changeStatus(); new→reviewing (qua
     * selfAssign) và resolved|closed→in_progress (qua reopen) nằm riêng.
     *
     * @var array<string, list<string>>
     */
    private const array FORWARD_TRANSITIONS = [
        'new' => ['closed'],
        'reviewing' => ['in_progress', 'closed'],
        'in_progress' => ['resolved', 'closed'],
    ];

    /**
     * Các trạng thái đích hợp lệ qua changeStatus() từ trạng thái hiện tại —
     * để UI chỉ hiện đúng nút được phép, cùng một nguồn với kiểm tra phía server.
     *
     * @return list<FeedbackStatus>
     */
    public function allowedTransitions(Feedback $feedback): array
    {
        return array_map(
            fn (string $value) => FeedbackStatus::from($value),
            self::FORWARD_TRANSITIONS[$feedback->status->value] ?? [],
        );
    }

    public function selfAssign(Feedback $feedback, User $handler): Feedback
    {
        return DB::transaction(function () use ($feedback, $handler) {
            $locked = $this->lock($feedback);

            if ($locked->assigned_to !== null) {
                throw FeedbackConflictException::alreadyAssigned();
            }

            if ($locked->status !== FeedbackStatus::New) {
                throw FeedbackConflictException::invalidTransition();
            }

            $locked->update([
                'assigned_to' => $handler->id,
                'status' => FeedbackStatus::Reviewing,
            ]);

            $this->logActivity($locked, ActivityType::AssignmentChange, $handler->id, null, (string) $handler->id);
            $this->logActivity($locked, ActivityType::StatusChange, $handler->id, FeedbackStatus::New->value, FeedbackStatus::Reviewing->value);

            return $locked;
        });
    }

    public function reassign(Feedback $feedback, User $admin, User $newHandler): Feedback
    {
        return DB::transaction(function () use ($feedback, $admin, $newHandler) {
            $locked = $this->lock($feedback);

            if (in_array($locked->status, [FeedbackStatus::Resolved, FeedbackStatus::Closed], true)) {
                throw FeedbackConflictException::alreadyClosedOrResolved();
            }

            $previousAssignee = $locked->assigned_to;

            $locked->update(['assigned_to' => $newHandler->id]);

            $this->logActivity(
                $locked,
                ActivityType::AssignmentChange,
                $admin->id,
                $previousAssignee !== null ? (string) $previousAssignee : null,
                (string) $newHandler->id,
            );

            $this->notify($newHandler, $locked, 'assigned', ['title' => $locked->title]);

            return $locked;
        });
    }

    public function changeStatus(Feedback $feedback, User $actor, FeedbackStatus $to, ?string $reason = null): Feedback
    {
        return DB::transaction(function () use ($feedback, $actor, $to, $reason) {
            $locked = $this->lock($feedback);
            $from = $locked->status;

            $allowed = self::FORWARD_TRANSITIONS[$from->value] ?? [];

            if (! in_array($to->value, $allowed, true)) {
                throw FeedbackConflictException::invalidTransition();
            }

            if ($to === FeedbackStatus::Resolved && ! $locked->messages()->where('visibility', MessageVisibility::Public)->exists()) {
                throw FeedbackConflictException::missingPublicReply();
            }

            $attributes = ['status' => $to];

            if ($to === FeedbackStatus::Closed) {
                $attributes['closed_reason'] = $reason;
                $attributes['closed_at'] = now();
            } elseif ($to === FeedbackStatus::Resolved) {
                $attributes['resolved_at'] = now();
            }

            $locked->update($attributes);

            $this->logActivity($locked, ActivityType::StatusChange, $actor->id, $from->value, $to->value, $reason);

            if (in_array($to, [FeedbackStatus::InProgress, FeedbackStatus::Resolved], true)) {
                $this->notify($locked->sender, $locked, 'status_changed', [
                    'title' => $locked->title,
                    'to_status' => $to->value,
                ]);
            }

            return $locked;
        });
    }

    public function reopen(Feedback $feedback, User $actor, string $reason): Feedback
    {
        return DB::transaction(function () use ($feedback, $actor, $reason) {
            $locked = $this->lock($feedback);
            $from = $locked->status;

            if (! in_array($from, [FeedbackStatus::Resolved, FeedbackStatus::Closed], true)) {
                throw FeedbackConflictException::notReopenable();
            }

            // Bảng 3.1: resolved mở lại để xử lý tiếp (in_progress); closed là
            // đóng nhầm nên quay về bước xem xét (reviewing).
            $to = $from === FeedbackStatus::Closed ? FeedbackStatus::Reviewing : FeedbackStatus::InProgress;

            $locked->update([
                'status' => $to,
                'reopened_count' => $locked->reopened_count + 1,
            ]);

            $this->logActivity($locked, ActivityType::Reopened, $actor->id, $from->value, $to->value, $reason);

            $recipientId = $locked->assigned_to;

            if ($recipientId !== null && $recipientId !== $actor->id) {
                $this->notify($locked->assignee, $locked, 'reopened', ['title' => $locked->title]);
            }

            return $locked;
        });
    }

    private function lock(Feedback $feedback): Feedback
    {
        /** @var Feedback */
        return Feedback::whereKey($feedback->id)->lockForUpdate()->firstOrFail();
    }

    private function logActivity(
        Feedback $feedback,
        ActivityType $type,
        int $actorId,
        ?string $from,
        ?string $to,
        ?string $note = null,
    ): void {
        FeedbackActivity::create([
            'feedback_id' => $feedback->id,
            'actor_id' => $actorId,
            'type' => $type,
            'from_value' => $from,
            'to_value' => $to,
            'note' => $note,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function notify(User $user, Feedback $feedback, string $type, array $data): void
    {
        FeedbackNotification::create([
            'user_id' => $user->id,
            'feedback_id' => $feedback->id,
            'type' => $type,
            'data' => $data,
        ]);

        Notification::send($user, new FeedbackPushNotification($feedback, $type));
    }
}
