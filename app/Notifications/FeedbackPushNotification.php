<?php

namespace App\Notifications;

use App\Models\Feedback;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Push khi có góp ý mới hoặc có trao đổi mới trong hội thoại — người nhận
 * được tính ở App\Services\FeedbackNotificationRecipients, độc lập với
 * FeedbackNotification (thông báo trong ứng dụng, mục 1.3).
 *
 * Cố tình KHÔNG implements ShouldQueue: hosting hiện chưa có queue worker
 * hay scheduler nào chạy (QUEUE_CONNECTION=database nhưng không ai xử lý
 * bảng jobs), nên notification queue được sẽ nằm im vĩnh viễn, không bao
 * giờ gửi. Gửi đồng bộ ngay trong request để không phụ thuộc hạ tầng đó.
 */
class FeedbackPushNotification extends Notification
{
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
