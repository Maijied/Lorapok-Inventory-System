/**
 * Per-user theme override.
 *
 * The shop sets a default on <html data-theme>; this lets one person on one
 * device choose otherwise without changing it for their colleagues.
 *
 * The *applying* half runs as a blocking inline script in the layout head, not
 * from here — by the time this module loads the page has already painted, and
 * a flash of the wrong theme on a till is worse than no toggle at all. This
 * file only wires the button.
 */

const KEY = 'lorapok.theme';

function current() {
    return document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
}

function render(button) {
    const isLight = current() === 'light';

    // The button offers the OTHER theme, so its label is the destination.
    button.querySelector('[data-theme-label]').textContent = isLight ? 'Dark mode' : 'Light mode';
    button.querySelector('[data-theme-icon-dark]').hidden = !isLight;
    button.querySelector('[data-theme-icon-light]').hidden = isLight;
}

function apply(theme) {
    document.documentElement.setAttribute('data-theme', theme);

    try {
        localStorage.setItem(KEY, theme);
    } catch {
        // Private mode, or storage disabled. The theme still applies for this
        // page; it just will not be remembered.
    }
}

function wire() {
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        if (button.dataset.themeWired) {
            return;
        }

        button.dataset.themeWired = '1';
        button.hidden = false;   // only appears once it can actually work
        render(button);

        button.addEventListener('click', () => {
            apply(current() === 'light' ? 'dark' : 'light');
            document.querySelectorAll('[data-theme-toggle]').forEach(render);
        });
    });
}

document.addEventListener('DOMContentLoaded', wire);

// Livewire swaps the DOM on navigation, so the buttons have to be re-wired.
document.addEventListener('livewire:navigated', wire);
