<x-layouts.marketing :seo="$seo">
    <section class="mx-auto max-w-4xl px-4 pb-8 pt-16 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-semibold tracking-tight text-[var(--color-text)] sm:text-4xl">Download</h1>
        <p class="mt-3 max-w-2xl text-[var(--color-muted)]">
            Lorapok Retail runs in a browser and installs to a home screen without any of
            this. These are for when you want it as a real application — a counter PC, a
            phone with no browser chrome, or your own hardware.
        </p>
    </section>

    <section class="mx-auto max-w-4xl px-4 pb-20 sm:px-6 lg:px-8">
        {{-- ── Install without downloading anything ──────────────────── --}}
        <div class="glass-panel p-6">
            <h2 class="text-lg font-semibold text-[var(--color-text)]">Nothing to install</h2>
            <p class="mt-2 text-sm leading-relaxed text-[var(--color-muted)]">
                Open your shop's address in Chrome or Safari and choose <strong
                class="text-[var(--color-text)]">Add to Home Screen</strong>. It opens
                like an app, keeps working when the connection drops, and updates itself.
                For most shops this is the right answer and there is nothing below worth
                the trouble.
            </p>
        </div>

        @if (! $catalog->hasRelease())
            {{-- Honest rather than a broken link: no release has been published
                 yet, and saying so beats a download button that 404s. --}}
            <x-ui.empty-state
                class="mt-6"
                title="No packaged builds yet"
                description="The installable builds are not published yet. The web app above is complete and is what shops use today.">
                <x-slot:action>
                    <x-ui.button :href="route('marketing.contact')">Ask to be told when they land</x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
        @else
            <div class="mt-6 flex flex-wrap items-baseline justify-between gap-3">
                <h2 class="text-lg font-semibold text-[var(--color-text)]">
                    Version {{ $latest['tag'] }}
                </h2>
                @if ($latest['notes_url'])
                    <a href="{{ $latest['notes_url'] }}" rel="noopener"
                       class="text-sm text-[var(--color-accent)] hover:underline">What changed</a>
                @endif
            </div>

            <div class="mt-4 space-y-4">
                @foreach ($platforms as $platform => $assets)
                    <div class="glass-panel p-5">
                        <h3 class="text-sm font-semibold text-[var(--color-text)]">{{ $platform }}</h3>

                        <ul class="mt-3 divide-y divide-[var(--color-border)]">
                            @foreach ($assets as $asset)
                                <li class="flex flex-wrap items-center justify-between gap-3 py-2.5">
                                    <span class="truncate text-sm text-[var(--color-text)]">{{ $asset['name'] }}</span>
                                    <span class="flex items-center gap-3">
                                        {{-- Sizes are read before clicking on a
                                             metered connection, so they use
                                             --color-text rather than muted. --}}
                                        <span class="tabular text-xs text-[var(--color-text)] font-[family-name:var(--font-mono)]">
                                            {{ $asset['size'] > 0 ? round($asset['size'] / 1048576, 1).' MB' : '' }}
                                        </span>
                                        <x-ui.button size="sm" variant="secondary" :href="$asset['url']" rel="noopener">
                                            Download
                                        </x-ui.button>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>

            <p class="mt-6 text-sm text-[var(--color-muted)]">
                Every release ships a <code>SHA256SUMS.txt</code>. The Android build is
                sideloaded rather than from the Play Store, so checking it is worth the
                thirty seconds.
            </p>
        @endif

        {{-- ── Running it yourself ───────────────────────────────────── --}}
        <div class="glass-panel mt-6 p-6">
            <h2 class="text-lg font-semibold text-[var(--color-text)]">Running it on your own hardware</h2>
            <p class="mt-2 text-sm leading-relaxed text-[var(--color-muted)]">
                The container image is public, and a chain with its own server can run the
                whole thing. You will need wildcard DNS and a wildcard certificate, because
                every shop is a subdomain — and backups that cover every shop's database
                together, since a partial restore leaves a shop that exists, is billed, and
                cannot open.
            </p>
            <div class="mt-4">
                <x-ui.button variant="secondary" :href="route('marketing.docs.show', ['section' => 'operator', 'page' => 'self-hosting'])">
                    Self-hosting guide
                </x-ui.button>
            </div>
        </div>
    </section>
</x-layouts.marketing>
