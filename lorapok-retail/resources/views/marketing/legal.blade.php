<x-layouts.marketing :seo="$seo">
    <article class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-semibold tracking-tight text-[var(--color-text)]">{{ $title }}</h1>
        <p class="mt-2 text-sm text-[var(--color-muted)]">Last updated {{ $updated }}</p>

        <div class="mt-10 space-y-8">
            @foreach ($sections as $heading => $paragraphs)
                <section>
                    <h2 class="text-lg font-semibold text-[var(--color-text)]">{{ $heading }}</h2>
                    @foreach ((array) $paragraphs as $paragraph)
                        <p class="mt-2.5 leading-relaxed text-[var(--color-muted)]">{{ $paragraph }}</p>
                    @endforeach
                </section>
            @endforeach
        </div>
    </article>
</x-layouts.marketing>
