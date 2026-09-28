@php
    $help = app(App\Domain\Help\HelpRepository::class)->forRoute(request()->route()?->getName());
@endphp

@if ($help)
    {{--
        Help for the screen you are on.

        A <details> rather than a JS drawer: it opens, closes, focuses and
        announces itself natively, and works if the bundle failed to load —
        which is exactly when someone is most likely to want help.

        Placed after the main content in the DOM so it does not sit between a
        cashier and the till in the tab order, but positioned visually at the
        edge of the screen.
    --}}
    <details class="group fixed bottom-4 right-4 z-40" name="help">
        <summary class="flex cursor-pointer items-center gap-2 rounded-full border border-[var(--color-border)] bg-[var(--color-bg-elevated)] px-4 py-2.5 text-sm font-medium text-[var(--color-text)] shadow-lg transition-colors hover:bg-[var(--color-bg-surface-hover)] [&::-webkit-details-marker]:hidden">
            <span aria-hidden="true">?</span>
            <span>Help</span>
        </summary>

        <div class="glass-panel absolute bottom-full right-0 mb-3 max-h-[70vh] w-[min(26rem,calc(100vw-2rem))] overflow-y-auto p-6 shadow-2xl">
            {{-- The rendered Markdown is ours, written in this repository and
                 never user-supplied, so it is emitted unescaped deliberately. --}}
            <div class="help-prose">{!! $help !!}</div>

            <p class="mt-6 border-t border-[var(--color-border)] pt-4 text-xs text-[var(--color-muted)]">
                More at <a href="{{ route('marketing.docs') }}" class="text-[var(--color-accent)] hover:underline">the handbook</a>.
            </p>
        </div>
    </details>
@endif
