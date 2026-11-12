<?php

namespace App\Http\Controllers;

use App\Enums\MessageVisibility;
use App\Http\Requests\StoreFeedbackMessageRequest;
use App\Models\Feedback;
use App\Models\FeedbackMessage;
use App\Models\FeedbackNotification;
use App\Notifications\FeedbackPushNotification;
use App\Services\FeedbackNotificationRecipients;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class FeedbackMessageController extends Controller
{
    /**
     * Thêm trao đổi (trả lời công khai hoặc ghi chú nội bộ) vào một góp ý.
     */
    public function store(
        StoreFeedbackMessageRequest $request,
        Feedback $feedback,
        FeedbackNotificationRecipients $recipients,
    ): RedirectResponse {
        $visibility = $request->visibility();
        $author = $request->user();

        FeedbackMessage::create([
            'feedback_id' => $feedback->id,
            'author_id' => $author->id,
            'body' => $request->input('body'),
            'visibility' => $visibility,
        ]);

        if ($visibility === MessageVisibility::Public) {
            $this->applyPublicReplyEffects($feedback, $author->id);
        }

        // Ghi chú nội bộ cũng push được, nhưng không bao giờ cho chủ góp ý
        // (nhân viên) — họ không có quyền xem nội dung internal (A4).
        Notification::send(
            $recipients->for($feedback, $author, includeOwner: $visibility === MessageVisibility::Public),
            new FeedbackPushNotification($feedback, 'replied'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Đã gửi trao đổi.')]);

        return back();
    }

    /**
     * Ghi chú nội bộ (A4) không chạy qua đây — chỉ nhánh public mới có thể
     * set first_response_at và tạo thông báo cho người gửi (mục 3.2, 4.4).
     */
    private function applyPublicReplyEffects(Feedback $feedback, int $authorId): void
    {
        $isReplyFromSomeoneElse = $authorId !== $feedback->user_id;

        if (! $isReplyFromSomeoneElse) {
            return;
        }

        if ($feedback->first_response_at === null) {
            $feedback->forceFill(['first_response_at' => now()])->save();
        }

        FeedbackNotification::create([
            'user_id' => $feedback->user_id,
            'feedback_id' => $feedback->id,
            'type' => 'new_reply',
            'data' => ['title' => $feedback->title],
        ]);
    }
}
