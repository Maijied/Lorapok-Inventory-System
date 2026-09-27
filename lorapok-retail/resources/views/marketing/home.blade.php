<x-layouts.marketing :seo="$seo">
    {{-- ── Hero ──────────────────────────────────────────────────────── --}}
    <section class="mx-auto max-w-6xl px-4 pb-16 pt-20 sm:px-6 lg:px-8">
        <p class="animate-fade-slide-up text-sm font-medium text-[var(--color-accent)]">
            Inventory and point of sale
        </p>

        <h1 class="animate-fade-slide-up stagger-1 mt-3 max-w-3xl text-4xl font-semibold leading-tight tracking-tight text-[var(--color-text)] sm:text-5xl">
            Know what you actually made today.
        </h1>

        <p class="animate-fade-slide-up stagger-2 mt-5 max-w-2xl text-lg text-[var(--color-muted)]">
            Most shop software tells you what you <em>took</em>. Lorapok Retail freezes the
            cost of every item at the moment it sells, so profit is a number you can read —
            not one you work out at the end of the month and hope is right.
        </p>

        <div class="animate-fade-slide-up stagger-3 mt-8 flex flex-wrap gap-3">
            <x-ui.button size="lg" :href="route('marketing.apply')">Start your shop</x-ui.button>
            <x-ui.button size="lg" variant="secondary" :href="route('marketing.pricing')">See pricing</x-ui.button>
        </div>

        <p class="animate-fade-slide-up stagger-4 mt-4 text-sm text-[var(--color-muted)]">
            {{ $trialDays }}-day trial. No card needed.
        </p>
    </section>

    {{-- ── The three things that are actually different ──────────────── --}}
    <section class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="grid gap-5 md:grid-cols-3">
            @foreach ([
                [
                    'Stock that cannot lie',
                    'Every movement is written once and never edited. The number on
                     the shelf and the number on screen are reconciled by a command
                     that fails loudly if they ever disagree.',
                ],
                [
                    'Margin you can see',
                    'Each sale line records what that item cost you, at the moment
                     it sold. Restocking cheaper next week cannot retroactively
                     change what last week earned.',
                ],
                [
                    'A till that keeps working',
                    'Serial numbers, split payments, partial refunds, and a shift
                     that balances. It installs to the home screen and opens
                     without a browser.',
                ],
            ] as $i => [$title, $body])
                <div class="glass-panel animate-fade-slide-up stagger-{{ min($i + 1, 4) }} p-6">
                    <h2 class="text-base font-semibold text-[var(--color-text)]">{{ $title }}</h2>
                    <p class="mt-2 text-sm leading-relaxed text-[var(--color-muted)]">{{ $body }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ── Who it is for ─────────────────────────────────────────────── --}}
    <section class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="glass-panel p-8">
            <h2 class="text-xl font-semibold tracking-tight text-[var(--color-text)]">
                Built for shops that sell serialised goods
            </h2>
            <p class="mt-3 max-w-3xl text-[var(--color-muted)]">
                A phone shop sells a ৳45,000 handset with an IMEI, a ৳200 cable with no
                serial at all, and takes half the payment in cash and half on bKash. Most
                systems handle one of those well. This one is built around all three.
            </p>

            <dl class="mt-8 grid gap-6 sm:grid-cols-3">
                @foreach ([
                    ['Own database per shop', 'Your data is not in a table shared with every other shop.'],
                    ['Roles that mean something', 'A cashier cannot change a price, void a sale, or see your margin.'],
                    ['Nothing is deleted', 'Mistakes are corrected with a reversing entry, so the trail survives.'],
                ] as [$term, $detail])
                    <div>
                        <dt class="text-sm font-medium text-[var(--color-text)]">{{ $term }}</dt>
                        <dd class="mt-1 text-sm text-[var(--color-muted)]">{{ $detail }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    {{-- ── Close ─────────────────────────────────────────────────────── --}}
    <section class="mx-auto max-w-6xl px-4 py-16 text-center sm:px-6 lg:px-8">
        <h2 class="text-2xl font-semibold tracking-tight text-[var(--color-text)]">
            Your shop, on its own address, today.
        </h2>
        <p class="mx-auto mt-3 max-w-xl text-[var(--color-muted)]">
            Tell us the name and we will set it up. You will be selling the same day.
        </p>
        <div class="mt-7 flex justify-center gap-3">
            <x-ui.button size="lg" :href="route('marketing.apply')">Start your shop</x-ui.button>
            <x-ui.button size="lg" variant="secondary" :href="route('marketing.contact')">Ask a question</x-ui.button>
        </div>
    </section>
</x-layouts.marketing>
