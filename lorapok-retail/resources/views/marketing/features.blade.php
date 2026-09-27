<x-layouts.marketing :seo="$seo">
    <section class="mx-auto max-w-4xl px-4 pb-10 pt-16 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-semibold tracking-tight text-[var(--color-text)] sm:text-4xl">
            What it actually does
        </h1>
        <p class="mt-3 text-lg text-[var(--color-muted)]">
            Written for someone deciding whether to move their shop onto it, so these are
            the things that are genuinely different — not a list every system could claim.
        </p>
    </section>

    <section class="mx-auto max-w-4xl px-4 pb-20 sm:px-6 lg:px-8">
        <div class="space-y-5">
            @foreach ([
                [
                    'Stock is a ledger, not a number',
                    'Every movement in and out is written once and never edited. The quantity
                     on screen is derived from that history, and a command reconciles the two
                     and fails if they disagree. When a count is wrong you can see exactly
                     which movement caused it, instead of guessing.',
                ],
                [
                    'Profit is frozen at the moment of sale',
                    'Each sale line stores what that item cost you when it sold. Buying the
                     same model cheaper next week cannot retroactively change what last
                     week earned — which is what makes a monthly margin figure trustworthy.',
                ],
                [
                    'The server decides the price',
                    'The till sends what was scanned, not what it costs. A cashier without
                     permission can send any price they like and it is ignored — not
                     rejected with an error they can learn to work around.',
                ],
                [
                    'Roles that actually restrict',
                    'A cashier sells and takes payment. They cannot change a price, void a
                     completed sale, or see your margin — the three levers used to cover a
                     till shortfall. The navigation does not even offer them.',
                ],
                [
                    'Serial numbers where they matter',
                    'Handsets carry an IMEI through purchase, sale and return. Accessories
                     do not need one and are not forced to have one.',
                ],
                [
                    'Nothing is deleted',
                    'A mistake is corrected by writing a reversing entry, so the original and
                     the correction both survive. You can always answer what happened, which
                     matters most exactly when something has gone wrong.',
                ],
                [
                    'Your own database',
                    'Each shop gets its own, not a row in a shared table. That boundary is
                     tested against real databases rather than assumed.',
                ],
                [
                    'It installs, and it opens without internet',
                    'Add it to the home screen and it opens like an app. If the connection
                     drops, the catalogue is still readable and the screen says so plainly
                     rather than failing at checkout.',
                ],
            ] as $i => [$title, $body])
                <div class="glass-panel animate-fade-slide-up stagger-{{ min($i + 1, 4) }} p-6">
                    <h2 class="text-base font-semibold text-[var(--color-text)]">{{ $title }}</h2>
                    <p class="mt-2 leading-relaxed text-[var(--color-muted)]">{{ $body }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-12 flex flex-wrap gap-3">
            <x-ui.button size="lg" :href="route('marketing.apply')">Start your shop</x-ui.button>
            <x-ui.button size="lg" variant="secondary" :href="route('marketing.pricing')">See pricing</x-ui.button>
        </div>
    </section>
</x-layouts.marketing>
