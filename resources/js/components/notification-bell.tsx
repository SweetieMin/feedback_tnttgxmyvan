import { Link, router } from '@inertiajs/react';
import { Bell, BellOff, BellRing } from 'lucide-react';
import { useEffect, useState } from 'react';
import NotificationController from '@/actions/App/Http/Controllers/NotificationController';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { usePushNotifications } from '@/hooks/use-push-notifications';
import { formatDateTime } from '@/lib/feedback';
import {
    describeNotification,
    NOTIFICATION_TYPE_ICONS,
} from '@/lib/notifications';
import { cn } from '@/lib/utils';
import { show } from '@/routes/feedback';
import { index } from '@/routes/notifications';
import type { FeedbackNotification } from '@/types';

const POLL_INTERVAL_MS = 45_000;

export function NotificationBell() {
    const [count, setCount] = useState(0);
    const [open, setOpen] = useState(false);
    const [recent, setRecent] = useState<FeedbackNotification[] | null>(null);
    const [busyId, setBusyId] = useState<number | null>(null);

    useEffect(() => {
        let cancelled = false;

        const load = async () => {
            if (document.visibilityState === 'hidden') {
                return;
            }

            try {
                const response = await fetch(
                    NotificationController.unreadCount.url(),
                    { headers: { Accept: 'application/json' } },
                );

                if (response.ok && !cancelled) {
                    const data = (await response.json()) as { count: number };
                    setCount(data.count);
                }
            } catch {
                // Mất mạng tạm thời: giữ số cũ, lượt poll sau sẽ thử lại.
            }
        };

        const onVisibilityChange = () => {
            if (document.visibilityState === 'visible') {
                void load();
            }
        };

        void load();
        const timer = window.setInterval(() => void load(), POLL_INTERVAL_MS);
        const stopListening = router.on('navigate', () => void load());
        document.addEventListener('visibilitychange', onVisibilityChange);

        return () => {
            cancelled = true;
            window.clearInterval(timer);
            stopListening();
            document.removeEventListener(
                'visibilitychange',
                onVisibilityChange,
            );
        };
    }, []);

    const handleOpenChange = (next: boolean) => {
        setOpen(next);

        if (!next) {
            return;
        }

        void (async () => {
            try {
                const response = await fetch(
                    NotificationController.recent.url(),
                    { headers: { Accept: 'application/json' } },
                );

                if (response.ok) {
                    const data = (await response.json()) as {
                        notifications: FeedbackNotification[];
                    };
                    setRecent(data.notifications);
                }
            } catch {
                setRecent([]);
            }
        })();
    };

    const openNotification = (notification: FeedbackNotification) => {
        const goToFeedback = () => {
            if (notification.feedback_id !== null) {
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

    const label = count > 0 ? `Thông báo (${count} chưa đọc)` : 'Thông báo';

    return (
        <DropdownMenu open={open} onOpenChange={handleOpenChange}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative"
                    aria-label={label}
                    title={label}
                >
                    <Bell />
                    {count > 0 && (
                        <span className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] leading-none font-semibold text-primary-foreground tabular-nums">
                            {count > 99 ? '99+' : count}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80">
                {recent === null ? (
                    <p className="px-2 py-3 text-sm text-muted-foreground">
                        Đang tải...
                    </p>
                ) : recent.length === 0 ? (
                    <p className="px-2 py-3 text-sm text-muted-foreground">
                        Chưa có thông báo nào.
                    </p>
                ) : (
                    recent.map((notification) => {
                        const Icon = NOTIFICATION_TYPE_ICONS[notification.type];
                        const unread = notification.read_at === null;

                        return (
                            <DropdownMenuItem
                                key={notification.id}
                                disabled={busyId !== null}
                                onSelect={() => openNotification(notification)}
                                className="items-start gap-2 whitespace-normal"
                            >
                                <Icon
                                    className={cn(
                                        'mt-0.5 shrink-0',
                                        unread
                                            ? 'text-foreground'
                                            : 'text-muted-foreground',
                                    )}
                                />
                                <span className="min-w-0 flex-1">
                                    <span
                                        className={cn(
                                            'block text-sm break-words',
                                            unread
                                                ? 'font-medium'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {describeNotification(notification)}
                                    </span>
                                    <span className="mt-0.5 block text-xs text-muted-foreground">
                                        {formatDateTime(
                                            notification.created_at,
                                        )}
                                    </span>
                                </span>
                            </DropdownMenuItem>
                        );
                    })
                )}
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href={index()} className="justify-center text-sm">
                        Xem tất cả
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function PushNotificationToggle() {
    const { supported, subscribed, loading, toggle } = usePushNotifications();

    if (!supported) {
        return null;
    }

    const label = subscribed
        ? 'Tắt thông báo đẩy trên thiết bị này'
        : 'Bật thông báo đẩy trên thiết bị này';

    return (
        <Button
            type="button"
            variant="ghost"
            size="icon"
            aria-label={label}
            title={label}
            disabled={loading}
            onClick={() => void toggle()}
        >
            {subscribed ? <BellRing /> : <BellOff />}
        </Button>
    );
}
