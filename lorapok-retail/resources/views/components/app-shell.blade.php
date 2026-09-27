@props([
    'brandName',
    'brandSub' => null,
    'brandHref' => null,
    'logoutRoute',
    'userName' => null,
    'userMeta' => null,
])

{{--
    The application shell: sidebar, topbar, content.

    Deliberately built from HTML that works with no JavaScript at all. The
    mobile drawer and the user menu are <details> elements, which browsers
    open, close, focus and announce natively. A cashier whose JS bundle failed
    to load still has a working nav — on a till that matters more than a
    prettier dropdown.
--}}
<div class="min-h-screen lg:grid lg:grid-cols-[16rem_1fr]">

    {{-- ── Sidebar (desktop) ─────────────────────────────────────────── --}}
    <aside class="hidden lg:flex lg:flex-col lg:border-r lg:border-[var(--color-border)] lg:bg-[var(--color-bg-elevated)]">
        <div class="flex h-16 items-center gap-3 border-b border-[var(--color-border)] px-5">
            <a href="{{ $brandHref ?? '#' }}" wire:navigate class="min-w-0">
                <span class="block truncate text-sm font-semibold text-[var(--color-text)]">{{ $brandName }}</span>
                @if ($brandSub)
                    <span class="block truncate text-xs text-[var(--color-muted)]">{{ $brandSub }}</span>
                @endif
            </a>
        </div>

        <nav aria-label="Main" class="flex-1 space-y-1 overflow-y-auto p-3">
            {{ $nav }}
        </nav>

        <div class="border-t border-[var(--color-border)] p-3">
            <x-ui.user-menu :name="$userName" :meta="$userMeta" :logout-route="$logoutRoute" />
        </div>
    </aside>

    <div class="flex min-w-0 flex-col">
        {{-- ── Topbar ────────────────────────────────────────────────── --}}
        <header class="flex h-16 items-center gap-3 border-b border-[var(--color-border)] bg-[var(--color-bg-elevated)] px-4 lg:hidden">
            <details class="relative" name="mobile-nav">
                <summary class="flex cursor-pointer items-center justify-center rounded-[var(--radius)] border border-[var(--color-border)] p-2 text-[var(--color-muted)] hover:text-[var(--color-text)] [&::-webkit-details-marker]:hidden">
                    <span class="sr-only">Open menu</span>
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="M3 5h14M3 10h14M3 15h14" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                    </svg>
                </summary>

                <nav aria-label="Main"
                     class="glass-panel absolute left-0 top-full z-40 mt-2 w-64 space-y-1 p-3 shadow-xl">
                    {{ $nav }}
                </nav>
            </details>

            <div class="min-w-0 flex-1">
                <span class="block truncate text-sm font-semibold text-[var(--color-text)]">{{ $brandName }}</span>
            </div>

            <x-ui.user-menu :name="$userName" :meta="$userMeta" :logout-route="$logoutRoute" compact />
        </header>

        <main id="main-content" class="min-w-0 flex-1">
            {{ $slot }}
        </main>
    </div>
</div>
