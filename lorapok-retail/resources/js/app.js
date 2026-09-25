// Self-hosted fonts — no CDN. A POS must render correctly on a flaky shop
// connection, and the PWA service worker precaches these.
import '@fontsource/dm-sans/400.css';
import '@fontsource/dm-sans/500.css';
import '@fontsource/dm-sans/600.css';
import '@fontsource/dm-sans/700.css';
import '@fontsource/jetbrains-mono/400.css';
import '@fontsource/jetbrains-mono/500.css';

import './offline';
import './scanner';

/**
 * Register the service worker.
 *
 * Only on a tenant subdomain and only over a secure context: the central
 * operator panel has nothing to gain from offline caching, and a service
 * worker cannot register over plain HTTP anyway (except on localhost, which
 * browsers treat as secure).
 */
if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {
            // A failed registration must never break the till. The app works
            // perfectly well without offline support.
        });
    });
}
