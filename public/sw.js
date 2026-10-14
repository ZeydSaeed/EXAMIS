/*
 * EXAMIS service worker — see docs/decisions/0008-pwa-cache-policy.md.
 *
 * Caches ONLY public static files:
 *   - a small precache (offline page, manifest, icons)
 *   - fingerprinted build assets under /build/assets/ (immutable)
 * Everything else — pages, Inertia responses, documents, receipts, form
 * submissions — always goes to the network and is never stored. When a
 * navigation fails offline, the static offline page is shown instead.
 */
const VERSION = 'examis-v1';
const STATIC_CACHE = `${VERSION}-static`;
const ASSET_CACHE = `${VERSION}-assets`;
const PRECACHE = [
    '/offline.html',
    '/manifest.webmanifest',
    '/favicon.svg',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
];
const MAX_ASSET_ENTRIES = 120;

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE)),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => !key.startsWith(VERSION))
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }

    if (event.data?.type === 'CLEAR_CACHES') {
        event.waitUntil(
            caches
                .keys()
                .then((keys) =>
                    Promise.all(keys.map((key) => caches.delete(key))),
                ),
        );
    }
});

function isBuildAsset(url) {
    return (
        url.origin === self.location.origin &&
        url.pathname.startsWith('/build/assets/')
    );
}

function isPrecached(url) {
    return (
        url.origin === self.location.origin && PRECACHE.includes(url.pathname)
    );
}

async function trimCache(name, maxEntries) {
    const cache = await caches.open(name);
    const keys = await cache.keys();

    for (const key of keys.slice(0, Math.max(0, keys.length - maxEntries))) {
        await cache.delete(key);
    }
}

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return; // never intercept writes
    }

    const url = new URL(request.url);

    if (isBuildAsset(url)) {
        event.respondWith(
            caches.open(ASSET_CACHE).then(async (cache) => {
                const cached = await cache.match(request);

                if (cached) {
                    return cached;
                }

                const response = await fetch(request);

                if (response.ok && response.type === 'basic') {
                    await cache.put(request, response.clone());
                    event.waitUntil(trimCache(ASSET_CACHE, MAX_ASSET_ENTRIES));
                }

                return response;
            }),
        );

        return;
    }

    if (isPrecached(url)) {
        event.respondWith(
            caches.match(request).then((cached) => cached ?? fetch(request)),
        );

        return;
    }

    // Full page navigations: network only, offline page on failure. Inertia
    // XHR visits are not navigations and fail visibly in the app instead.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match('/offline.html')),
        );
    }
});
