const offlineCache = 'ostanyaar-offline-v1';
const offlinePage = '/pwa/offline.html';
const entryPaths = new Set(['/', '/counties', '/install']);

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(offlineCache);
        await cache.add(new Request(offlinePage, { credentials: 'omit', cache: 'reload' }));
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keys = await caches.keys();
        await Promise.all(keys
            .filter((key) => key.startsWith('ostanyaar-offline-') && key !== offlineCache)
            .map((key) => caches.delete(key)));
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET' || request.mode !== 'navigate') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin || !entryPaths.has(url.pathname)) return;

    // Dynamic pages always come from the network, including authenticated HTML.
    event.respondWith(fetch(request).catch(async () => {
        const cache = await caches.open(offlineCache);
        return (await cache.match(offlinePage)) ?? Response.error();
    }));
});
