// Service worker thuần cho web push — không qua Vite vì cần nằm ở gốc domain
// để có scope toàn site. Payload JSON do App\Notifications\Channels\WebPushChannel
// gửi: { title, body, url }.

self.addEventListener('push', (event) => {
    let data = { title: 'Thông báo mới', body: '', url: '/' };

    if (event.data) {
        try {
            data = { ...data, ...event.data.json() };
        } catch {
            data.body = event.data.text();
        }
    }

    event.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body,
            icon: '/images/tntt-my-van.png',
            badge: '/images/tntt-my-van.png',
            data: { url: data.url },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = event.notification.data?.url ?? '/';

    event.waitUntil(
        self.clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then((clients) => {
                const existing = clients.find((client) => client.url === url);

                if (existing) {
                    return existing.focus();
                }

                return self.clients.openWindow(url);
            }),
    );
});
