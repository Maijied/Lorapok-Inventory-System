@props([
    'name' => null,
    'meta' => null,
    'logoutRoute',
    'compact' => false,
])

{{--
    Who am I, and how do I get out.

    A <details> rather than a JS dropdown: Escape closes it, Tab moves through
    it and screen readers announce its expanded state, all without a line of
    script. Sign-out is a real POST form with a CSRF token — a GET link would
    let any page log a cashier out mid-sale.
--}}
<details class="relative" name="user-menu">
    <summary class="flex cursor-pointer items-center gap-2 rounded-[var(--radius)] px-2 py-2 text-left hover:bg-[var(--color-bg-surface-hover)] [&::-webkit-details-marker]:hidden">
        <span aria-hidden="true"
              class="flex size-8 shrink-0 items-center justify-center rounded-full bg-[color-mix(in_srgb,var(--color-accent)_22%,transparent)] text-xs font-semibold text-[var(--color-text)]">
            {{ mb_strtoupper(mb_substr((string) ($name ?? '?'), 0, 1)) }}
        </span>

        @unless ($compact)
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm text-[var(--color-text)]">{{ $name }}</span>
                @if ($meta)
                    <span class="block truncate text-xs text-[var(--color-muted)]">{{ $meta }}</span>
                @endif
            </span>
        @endunless

        <span class="sr-only">Account menu</span>
    </summary>

    {{-- The two placements are emitted as mutually exclusive class sets rather
         than one overriding the other. Tailwind resolves conflicting utilities
         by their order in the generated stylesheet, not by their order in this
         attribute, so `bottom-full` and `bottom-auto` together would resolve
         in a way this template cannot control — and could flip on an upgrade. --}}
    <div @class([
        'glass-panel absolute right-0 z-40 w-56 p-2 shadow-xl',
        // In the topbar the menu hangs below the avatar...
        'top-full mt-2' => $compact,
        // ...in the sidebar footer it rises above it.
        'bottom-full mb-2' => ! $compact,
    ])>
        @if ($name)
            <div class="border-b border-[var(--color-border)] px-3 pb-2 pt-1">
                <p class="truncate text-sm text-[var(--color-text)]">{{ $name }}</p>
                @if ($meta)
                    <p class="truncate text-xs text-[var(--color-muted)]">{{ $meta }}</p>
                @endif
            </div>
        @endif

        <form method="POST" action="{{ $logoutRoute }}" class="pt-1">
            @csrf
            <button type="submit"
                    class="w-full rounded-[var(--radius)] px-3 py-2 text-left text-sm text-[var(--color-muted)] transition-colors hover:bg-[var(--color-bg-surface-hover)] hover:text-[var(--color-text)]">
                Sign out
            </button>
        </form>
    </div>
</details>
