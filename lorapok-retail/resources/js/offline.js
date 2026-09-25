/**
 * Offline awareness.
 *
 * v1 is deliberately online-only for completing sales: Livewire is a
 * server round-trip on every interaction, so a cart cannot be checked out
 * without a connection. What the service worker gives us is a till that
 * still *opens* and still shows the catalogue — and, crucially, a cashier
 * who is told immediately rather than discovering it at checkout.
 */

const BANNER = '[data-offline-banner]';

function apply(online) {
    const banner = document.querySelector(BANNER);

    if (banner) {
        banner.hidden = online;
    }

    document.documentElement.classList.toggle('is-offline', !online);

    // Disable anything that needs the server, so a cashier cannot queue up
    // an action that is going to fail.
    document.querySelectorAll('[data-requires-online]').forEach((el) => {
        el.toggleAttribute('disabled', !online);
        el.setAttribute('aria-disabled', String(!online));
    });
}

function sync() {
    apply(navigator.onLine);
}

window.addEventListener('online', sync);
window.addEventListener('offline', sync);
document.addEventListener('DOMContentLoaded', sync);
// Livewire swaps DOM on navigation, so re-apply after each page change.
document.addEventListener('livewire:navigated', sync);

export { sync };
