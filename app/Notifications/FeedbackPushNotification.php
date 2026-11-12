<?php

namespace App\Notifications;

use App\Models\Feedback;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Push khi có góp ý mới hoặc có trao đổi mới trong hội thoại — người nhận
 * được tính ở App\Services\FeedbackNotificationRecipients, độc lập với
 * FeedbackNotification (thông báo trong ứng dụng, mục 1.3).
 */
class FeedbackPushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Feedback $feedback,
        public string $event,
    ) {}

    /**
     * @return list<class-string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => $this->event === 'created' ? 'Góp ý mới' : 'Có trao đổi mới',
            'body' => Str::limit($this->feedback->title, 100),
            'url' => route('feedback.show', $this->feedback),
        ];
    }
}
