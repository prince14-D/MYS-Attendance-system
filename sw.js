const SW_VERSION = 'v3';
const STATIC_CACHE = `mys-attendance-static-${SW_VERSION}`;
const RUNTIME_CACHE = `mys-attendance-runtime-${SW_VERSION}`;
const STATIC_ASSETS = [
    './',
    './index.php',
    './admin.php',
    './assets/styles.css',
    './assets/app-icon.svg',
    './manifest.webmanifest'
];

function isCacheableResponse(response) {
    return response && response.ok && response.status < 400;
}

async function broadcastMessage(message) {
    const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

    for (const client of clients) {
        client.postMessage(message);
    }
}

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(STATIC_CACHE);
        await cache.addAll(STATIC_ASSETS);
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keys = await caches.keys();
        const allowList = new Set([STATIC_CACHE, RUNTIME_CACHE]);

        await Promise.all(keys
            .filter((key) => !allowList.has(key))
            .map((key) => caches.delete(key)));

        await self.clients.claim();
    })());
});

self.addEventListener('message', (event) => {
    if (!event.data || typeof event.data !== 'object') {
        return;
    }

    if (event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }

    if (event.data.type === 'SYNC_QUEUE') {
        broadcastMessage({ type: 'SYNC_QUEUE' });
    }
});

self.addEventListener('sync', (event) => {
    if (event.tag !== 'attendance-sync') {
        return;
    }

    event.waitUntil(broadcastMessage({ type: 'SYNC_QUEUE' }));
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    // HTML navigation: network first with cached app shell fallback.
    if (request.mode === 'navigate') {
        event.respondWith((async () => {
            try {
                const response = await fetch(request);

                if (isCacheableResponse(response)) {
                    const cache = await caches.open(RUNTIME_CACHE);
                    cache.put('./index.php', response.clone());
                }

                return response;
            } catch (error) {
                const fallback = await caches.match('./index.php');
                return fallback || Response.error();
            }
        })());
        return;
    }

    const isStaticAsset = /\.(?:css|js|svg|png|jpg|jpeg|webp|gif|woff2?)$/i.test(url.pathname)
        || url.pathname.endsWith('/manifest.webmanifest');

    // Static assets: stale-while-revalidate for fast startup.
    if (isStaticAsset) {
        event.respondWith((async () => {
            const cache = await caches.open(STATIC_CACHE);
            const cached = await cache.match(request);
            const networkPromise = fetch(request)
                .then((response) => {
                    if (isCacheableResponse(response)) {
                        cache.put(request, response.clone());
                    }
                    return response;
                })
                .catch(() => null);

            if (cached) {
                event.waitUntil(networkPromise);
                return cached;
            }

            const network = await networkPromise;
            return network || Response.error();
        })());
        return;
    }

    // Other same-origin requests: network first, cache fallback.
    event.respondWith((async () => {
        const cache = await caches.open(RUNTIME_CACHE);

        try {
            const response = await fetch(request);

            if (isCacheableResponse(response)) {
                cache.put(request, response.clone());
            }

            return response;
        } catch (error) {
            const cached = await cache.match(request);
            return cached || Response.error();
        }
    })());
});
