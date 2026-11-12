import {
    MessageSquareReply,
    RefreshCw,
    RotateCcw,
    UserRoundCheck,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { isFeedbackStatus, STATUS_LABELS } from '@/lib/feedback';
import type { FeedbackNotification, NotificationType } from '@/types';

export const NOTIFICATION_TYPE_ICONS: Record<NotificationType, LucideIcon> = {
    assigned: UserRoundCheck,
    new_reply: MessageSquareReply,
    status_changed: RefreshCw,
    reopened: RotateCcw,
};

export function describeNotification(
    notification: FeedbackNotification,
): string {
    const title =
        notification.data.title ?? notification.feedback?.title ?? 'một góp ý';

    switch (notification.type) {
        case 'assigned':
            return `Bạn được giao xử lý góp ý “${title}”.`;
        case 'new_reply':
            return `Có trả lời mới cho góp ý “${title}”.`;
        case 'status_changed': {
            const status = notification.data.to_status;

            return isFeedbackStatus(status)
                ? `Góp ý “${title}” đã chuyển sang “${STATUS_LABELS[status]}”.`
                : `Góp ý “${title}” vừa được cập nhật trạng thái.`;
        }
        case 'reopened':
            return `Góp ý “${title}” đã được mở lại.`;
    }
}
