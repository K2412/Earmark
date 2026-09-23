// Earmark service worker — minimal, honest offline handling (task #1263).
//
// Deliberately network-first with no financial-data caching: household data is
// never stored in the cache, so the app makes no unexpected requests and never
// serves stale financial figures. Only a tiny offline fallback page is cached.
const OFFLINE_URL = '/offline.html';
const CACHE = 'earmark-shell-v1';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.add(OFFLINE_URL)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))),
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Only handle top-level navigations; everything else goes straight to the network.
    if (request.mode !== 'navigate') {
        return;
    }

    event.respondWith(
        fetch(request).catch(() => caches.match(OFFLINE_URL)),
    );
});
