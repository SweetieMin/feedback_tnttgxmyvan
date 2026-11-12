import { Head, router } from '@inertiajs/react';
import { BellOff, Check } from 'lucide-react';
import { useState } from 'react';
import NotificationController from '@/actions/App/Http/Controllers/NotificationController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/feedback';
import {
    describeNotification,
    NOTIFICATION_TYPE_ICONS,
} from '@/lib/notifications';
import { cn } from '@/lib/utils';
import { show } from '@/routes/feedback';
import { index } from '@/routes/notifications';
import type { FeedbackNotification } from '@/types';

export default function NotificationsIndex({
    notifications,
}: {
    notifications: FeedbackNotification[];
}) {
    const [busyId, setBusyId] = useState<number | null>(null);
    const unreadCount = notifications.filter((n) => n.read_at === null).length;

    const markRead = (notification: FeedbackNotification, open: boolean) => {
        const goToFeedback = () => {
            if (open && notification.feedback_id !== null) {
                router.visit(show(notification.feedback_id));
            }
        };

        if (notification.read_at !== null) {
            goToFeedback();

            return;
        }

        setBusyId(notification.id);
        router.post(
            NotificationController.markRead.url(notification.id),
            {},
            {
                preserveScroll: true,
                onSuccess: goToFeedback,
                onFinish: () => setBusyId(null),
            },
        );
    };

    return (
        <>
            <Head title="Thông báo" />

            <div className="flex w-full max-w-3xl flex-col gap-6 p-4">
                <Heading
                    title="Thông báo"
                    description={
                        unreadCount > 0
                            ? `Bạn có ${unreadCount} thông báo chưa đọc.`
                            : 'Bạn đã đọc hết thông báo.'
                    }
                />

                {notifications.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed px-6 py-16 text-center">
                        <BellOff className="size-8 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            Chưa có thông báo nào.
                        </p>
                    </div>
                ) : (
                    <ul className="divide-y rounded-xl border">
                        {notifications.map((notification) => {
                            const Icon =
                                NOTIFICATION_TYPE_ICONS[notification.type];
                            const unread = notification.read_at === null;

                            return (
                                <li
                                    key={notification.id}
                                    className={cn(
                                        'flex items-start gap-3 px-4 py-3',
                                        unread && 'bg-muted/40',
                                    )}
                                >
                                    <Icon
                                        className={cn(
                                            'mt-0.5 size-4 shrink-0',
                                            unread
                                                ? 'text-foreground'
                                                : 'text-muted-foreground',
                                        )}
                                    />
                                    <button
                                        type="button"
                                        className="min-w-0 flex-1 text-left"
                                        disabled={
                                            notification.feedback_id === null
                                        }
                                        onClick={() =>
                                            markRead(notification, true)
                                        }
                                    >
                                        <p
                                            className={cn(
                                                'text-sm break-words',
                                                unread
                                                    ? 'font-medium'
                                                    : 'text-muted-foreground',
                                            )}
                                        >
                                            {describeNotification(notification)}
                                        </p>
                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                            {formatDateTime(
                                                notification.created_at,
                                            )}
                                        </p>
                                    </button>
                                    {unread && (
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            className="shrink-0"
                                            disabled={busyId !== null}
                                            onClick={() =>
                                                markRead(notification, false)
                                            }
                                        >
                                            <Check />
                                            <span className="sr-only sm:not-sr-only">
                                                Đã đọc
                                            </span>
                                        </Button>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </>
    );
}

NotificationsIndex.layout = {
    breadcrumbs: [{ title: 'Thông báo', href: index() }],
};
