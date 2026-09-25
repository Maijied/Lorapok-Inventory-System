/**
 * Lorapok Retail service worker.
 *
 * Written by hand rather than generated. vite-plugin-pwa assumes a Vite SPA
 * with an index.html entry, which a Laravel app does not have; and the
 * caching rules here are few enough that being able to read them beats
 * having them produced for us. Getting one of these wrong corrupts a till.
 *
 * Scope of v1: the shop opens and the catalogue is readable offline, but a
 * sale cannot be completed. Livewire is a server round-trip on every
 * interaction, so an offline checkout is not a caching problem — it needs a
 * separate offline client, which `sales.idempotency_key` already prepares
 * for.
 */

const VERSION = 'v1';
const PAGES = `lorapok-pages-${VERSION}`;
const ASSETS = `lorapok-assets-${VERSION}`;
const KEEP = [PAGES, ASSETS];

self.addEventListener('install', (event) => {
    // Take over as soon as a new version is deployed rather than waiting for
    // every till to be closed.
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            const names = await caches.keys();

            await Promise.all(
                names.filter((name) => !KEEP.includes(name)).map((name) => caches.delete(name)),
            );

            await self.clients.claim();
        })(),
    );
});

/** Hashed build output never changes under the same URL. */
function isBuildAsset(url) {
    return url.pathname.startsWith('/build/assets/');
}

/**
 * Anything that must always hit the network.
 *
 * Livewire's update endpoint above all: serving a stale component payload
 * corrupts component state in ways that are very hard to diagnose — a cart
 * that silently reverts, a sale that appears to save twice.
 */
function isNeverCacheable(request, url) {
    return (
        request.method !== 'GET' ||
        url.pathname.includes('/livewire/') ||
        url.pathname.startsWith('/admin/') ||
        url.pathname === '/manifest.webmanifest'
    );
}

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Never touch another origin's requests.
    if (url.origin !== self.location.origin) {
        return;
    }

    if (isNeverCacheable(request, url)) {
        return;
    }

    if (isBuildAsset(url)) {
        event.respondWith(cacheFirst(request, ASSETS));

        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(networkFirst(request, PAGES));
    }
});

async function cacheFirst(request, cacheName) {
    const cached = await caches.match(request);

    if (cached) {
        return cached;
    }

    const response = await fetch(request);

    if (response.ok) {
        const cache = await caches.open(cacheName);
        cache.put(request, response.clone());
    }

    return response;
}

/**
 * Prefer the network so a cashier sees current stock, but fall back to the
 * last good copy rather than a browser error page when the shop's
 * connection drops.
 */
async function networkFirst(request, cacheName) {
    try {
        const response = await fetch(request);

        if (response.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, response.clone());
        }

        return response;
    } catch (e) {
        const cached = await caches.match(request);

        if (cached) {
            return cached;
        }

        // Nothing cached and no network: say so in the shop's own language
        // rather than showing a browser error.
        return new Response(
            '<!doctype html><meta charset="utf-8"><title>Offline</title>' +
                '<body style="background:#06080d;color:#f1f5fb;font-family:system-ui;padding:3rem;text-align:center">' +
                '<h1 style="font-size:1.25rem">You are offline</h1>' +
                '<p style="color:#8b9bb8">This page has not been opened on this device yet, so there is nothing saved to show.</p>' +
                '</body>',
            { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } },
        );
    }
}
