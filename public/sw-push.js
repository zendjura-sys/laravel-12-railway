/*
 * Мінімальний service worker — лише для push. НЕ кешує жодних файлів і не
 * перехоплює fetch: PWA-манифест навмисно без повноцінного service worker
 * (щоб не тримати застарілий JS після деплою з хешованими іменами файлів
 * Vite), а push-повідомлення без service worker браузер узагалі не доставить.
 * Цей файл — компроміс: слухає тільки два події, нічого не кешує.
 */

self.addEventListener('push', (event) => {
    let payload = {};
    try {
        payload = event.data ? event.data.json() : {};
    } catch {
        payload = { title: 'Monsory Connect', body: event.data ? event.data.text() : '' };
    }

    const title = payload.title || 'Monsory Connect';
    const options = {
        body: payload.body || '',
        icon: '/images/icons/icon-192.png',
        badge: '/images/icons/icon-192.png',
        data: { url: payload.url || '/notifications' },
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/notifications';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
            for (const client of list) {
                if (client.url.includes(url) && 'focus' in client) {
                    return client.focus();
                }
            }
            return clients.openWindow(url);
        }),
    );
});
