<x-layouts.marketing :seo="$seo">
    <section class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-semibold tracking-tight text-[var(--color-text)]">Handbook</h1>
        <p class="mt-3 text-[var(--color-muted)]">
            How the system actually works. The same pages the in-app help shows,
            so there is one explanation rather than two that drift apart.
        </p>

        <div class="mt-10 space-y-8">
            @foreach ($sections as $section => $pages)
                <section>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-[var(--color-muted)]">
                        {{ $section === 'shop' ? 'Running a shop' : 'For operators' }}
                    </h2>

                    <ul class="mt-3 divide-y divide-[var(--color-border)]">
                        @foreach ($pages as $page)
                            <li>
                                <a href="{{ route('marketing.docs.show', ['section' => $section, 'page' => $page]) }}"
                                   class="flex items-center justify-between py-3 text-[var(--color-text)] hover:text-[var(--color-accent)]">
                                    <span>{{ app(App\Domain\Help\HelpRepository::class)->titleFor("{$section}/{$page}") }}</span>
                                    <span aria-hidden="true" class="text-[var(--color-muted)]">→</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    </section>
</x-layouts.marketing>
