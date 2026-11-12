// Đăng ký/huỷ đăng ký web push. Dùng fetch thuần (không qua Inertia router)
// vì đây là hành động nền, không phải điều hướng trang — nên tự đính kèm
// X-XSRF-TOKEN từ cookie giống cách axios/Inertia làm tự động.

import PushSubscriptionController from '@/actions/App/Http/Controllers/PushSubscriptionController';

function readCookie(name: string): string | null {
    const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));

    return match ? decodeURIComponent(match[1]) : null;
}

function csrfHeaders(): Record<string, string> {
    const token = readCookie('XSRF-TOKEN');

    return token ? { 'X-XSRF-TOKEN': token } : {};
}

function urlBase64ToUint8Array(base64String: string): Uint8Array<ArrayBuffer> {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding)
        .replace(/-/g, '+')
        .replace(/_/g, '/');
    const rawData = window.atob(base64);
    const output = new Uint8Array(new ArrayBuffer(rawData.length));

    for (let i = 0; i < rawData.length; i++) {
        output[i] = rawData.charCodeAt(i);
    }

    return output;
}

export function isPushSupported(): boolean {
    return (
        'serviceWorker' in navigator &&
        'PushManager' in window &&
        'Notification' in window
    );
}

export async function getExistingPushSubscription(): Promise<PushSubscription | null> {
    if (!isPushSupported()) {
        return null;
    }

    const registration =
        await navigator.serviceWorker.getRegistration('/sw.js');

    return (await registration?.pushManager.getSubscription()) ?? null;
}

export async function subscribeToPush(
    vapidPublicKey: string,
): Promise<PushSubscription> {
    const permission = await Notification.requestPermission();

    if (permission !== 'granted') {
        throw new Error('Quyền thông báo bị từ chối.');
    }

    const registration = await navigator.serviceWorker.register('/sw.js');
    const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
    });

    await fetch(PushSubscriptionController.store.url(), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            ...csrfHeaders(),
        },
        body: JSON.stringify(subscription.toJSON()),
        credentials: 'same-origin',
    });

    return subscription;
}

export async function unsubscribeFromPush(): Promise<void> {
    const subscription = await getExistingPushSubscription();

    if (!subscription) {
        return;
    }

    const endpoint = subscription.endpoint;
    await subscription.unsubscribe();

    await fetch(PushSubscriptionController.destroy.url(), {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            ...csrfHeaders(),
        },
        body: JSON.stringify({ endpoint }),
        credentials: 'same-origin',
    });
}
