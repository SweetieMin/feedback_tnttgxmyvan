import { Link, router } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useEffect, useState } from 'react';
import NotificationController from '@/actions/App/Http/Controllers/NotificationController';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/notifications';

const POLL_INTERVAL_MS = 45_000;

export function NotificationBell() {
    const [count, setCount] = useState(0);

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

    const label = count > 0 ? `Thông báo (${count} chưa đọc)` : 'Thông báo';

    return (
        <Button
            asChild
            variant="ghost"
            size="icon"
            className="relative"
            aria-label={label}
            title={label}
        >
            <Link href={index()}>
                <Bell />
                {count > 0 && (
                    <span className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] leading-none font-semibold text-primary-foreground tabular-nums">
                        {count > 99 ? '99+' : count}
                    </span>
                )}
            </Link>
        </Button>
    );
}
