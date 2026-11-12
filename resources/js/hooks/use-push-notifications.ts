import { usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
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
            }
        } catch {
            // Người dùng từ chối quyền hoặc trình duyệt chặn — giữ nguyên trạng thái cũ.
        } finally {
            setLoading(false);
        }
    }, [loading, subscribed, supported, vapidPublicKey]);

    return { supported, subscribed, loading, toggle };
}
