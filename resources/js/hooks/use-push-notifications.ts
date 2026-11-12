import { usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import {
    getExistingPushSubscription,
    isPushSupported,
    subscribeToPush,
    unsubscribeFromPush,
} from '@/lib/push-subscription';

export function usePushNotifications() {
    const { vapidPublicKey } = usePage().props;
    const supported = isPushSupported() && Boolean(vapidPublicKey);
    const [subscribed, setSubscribed] = useState(false);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!supported) {
            return;
        }

        let cancelled = false;

        void getExistingPushSubscription().then((subscription) => {
            if (!cancelled) {
                setSubscribed(subscription !== null);
            }
        });

        return () => {
            cancelled = true;
        };
    }, [supported]);

    const toggle = useCallback(async () => {
        if (!supported || !vapidPublicKey || loading) {
            return;
        }

        setLoading(true);

        try {
            if (subscribed) {
                await unsubscribeFromPush();
                setSubscribed(false);
            } else {
                await subscribeToPush(vapidPublicKey);
                setSubscribed(true);
                toast.success('Đã bật thông báo đẩy trên thiết bị này.');
            }
        } catch (error) {
            // Người dùng từ chối quyền, trình duyệt chặn, hoặc server không
            // lưu được subscription — báo rõ thay vì im lặng để không tưởng
            // nhầm là đã bật thành công.
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Không bật được thông báo đẩy.',
            );
        } finally {
            setLoading(false);
        }
    }, [loading, subscribed, supported, vapidPublicKey]);

    return { supported, subscribed, loading, toggle };
}
