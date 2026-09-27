{{--
    Per-user theme override.

    The shop chooses a default — light, because counters are bright — but a
    cashier on a sunlit counter and an owner doing books at night want
    different things, and they may be the same shop. So the override is
    per-browser rather than shop-wide, which also means a cashier cannot
    change the theme for everyone.

    Stored in localStorage and applied before paint by the inline script in the
    layout head. Without JavaScript this button simply does nothing and the
    shop default stands — a preference, not a function.
--}}
<button type="button"
        data-theme-toggle
        hidden
        class="flex w-full items-center gap-2 rounded-[var(--radius)] px-3 py-2 text-left text-sm text-[var(--color-muted)] transition-colors hover:bg-[var(--color-bg-surface-hover)] hover:text-[var(--color-text)]">
    <span aria-hidden="true" data-theme-icon-dark>
        <svg width="16" height="16" viewBox="0 0 20 20" fill="none">
            <path d="M16 11.5A6.5 6.5 0 0 1 8.5 4a6.5 6.5 0 1 0 7.5 7.5Z"
                  stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
        </svg>
    </span>
    <span aria-hidden="true" data-theme-icon-light hidden>
        <svg width="16" height="16" viewBox="0 0 20 20" fill="none">
            <circle cx="10" cy="10" r="3.5" stroke="currentColor" stroke-width="1.5"/>
            <path d="M10 2v2m0 12v2M2 10h2m12 0h2M4.5 4.5l1.4 1.4m8.2 8.2 1.4 1.4m0-11-1.4 1.4m-8.2 8.2-1.4 1.4"
                  stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
    </span>
    <span data-theme-label>Dark mode</span>
</button>
