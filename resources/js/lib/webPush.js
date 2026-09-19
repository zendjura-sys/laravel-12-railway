/**
 * Підписка/відписка від push-сповіщень у браузері. Сама відправка —
 * серверна (App\Support\WebPushSender); тут лише service worker і
 * PushManager.
 */

export function isWebPushSupported() {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
}

export function pushPermission() {
    return isWebPushSupported() ? Notification.permission : 'unsupported';
}

// applicationServerKey очікує Uint8Array, а сервер віддає base64url.
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(base64);
    return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
}

export async function getExistingSubscription() {
    if (!isWebPushSupported()) return null;
    const registration = await navigator.serviceWorker.getRegistration('/sw-push.js');
    return registration ? registration.pushManager.getSubscription() : null;
}

export async function subscribeToPush(publicKey) {
    const registration = await navigator.serviceWorker.register('/sw-push.js');
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
        throw new Error('permission-denied');
    }

    const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(publicKey),
    });

    await window.axios.post(route('push-subscriptions.store'), subscription.toJSON());

    return subscription;
}

export async function unsubscribeFromPush() {
    const subscription = await getExistingSubscription();
    if (!subscription) return;

    const endpoint = subscription.endpoint;
    await subscription.unsubscribe();
    await window.axios.delete(route('push-subscriptions.destroy'), { data: { endpoint } });
}
