import type { FeedbackActivity, FeedbackStatus } from '@/types';

export const STATUS_LABELS: Record<FeedbackStatus, string> = {
    new: 'Mới',
    reviewing: 'Đang xem xét',
    in_progress: 'Đang xử lý',
    resolved: 'Đã giải quyết',
    closed: 'Đã đóng',
};

export const STATUS_ORDER: FeedbackStatus[] = [
    'new',
    'reviewing',
    'in_progress',
    'resolved',
    'closed',
];

export const STATUS_BADGE_CLASSES: Record<FeedbackStatus, string> = {
    new: 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-300',
    reviewing:
        'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300',
    in_progress:
        'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-900 dark:bg-violet-950 dark:text-violet-300',
    resolved:
        'border-green-200 bg-green-50 text-green-700 dark:border-green-900 dark:bg-green-950 dark:text-green-300',
    closed: 'border-neutral-200 bg-neutral-100 text-neutral-600 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-400',
};

export function isFeedbackStatus(value: unknown): value is FeedbackStatus {
    return (
        typeof value === 'string' && (STATUS_ORDER as string[]).includes(value)
    );
}

const dateTimeFormatter = new Intl.DateTimeFormat('vi-VN', {
    dateStyle: 'short',
    timeStyle: 'short',
});

export function formatDateTime(value: string): string {
    return dateTimeFormatter.format(new Date(value));
}

function statusLabel(value: string | null): string {
    return isFeedbackStatus(value) ? STATUS_LABELS[value] : (value ?? '');
}

/**
 * `assignment_change` lưu id người phụ trách trong from/to_value nên chỉ suy
 * ra được "tự nhận" hay "được chuyển", không hiển thị id thô.
 */
export function describeActivity(activity: FeedbackActivity): string {
    switch (activity.type) {
        case 'status_change':
            if (activity.from_value === null) {
                return 'đã gửi góp ý';
            }

            return `chuyển trạng thái từ "${statusLabel(activity.from_value)}" sang "${statusLabel(activity.to_value)}"`;
        case 'assignment_change':
            return activity.to_value === String(activity.actor_id)
                ? 'đã nhận xử lý góp ý'
                : 'đã chuyển người phụ trách';
        case 'reopened':
            return 'đã mở lại yêu cầu';
    }
}
