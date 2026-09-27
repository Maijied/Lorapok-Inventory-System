<x-layouts.marketing :seo="$seo">
    <section class="mx-auto max-w-lg px-4 py-24 text-center sm:px-6 lg:px-8">
        <p class="text-4xl" aria-hidden="true">✓</p>
        <h1 class="mt-4 text-2xl font-semibold tracking-tight text-[var(--color-text)]">{{ $heading }}</h1>
        <p class="mt-3 text-[var(--color-muted)]">{{ $body }}</p>

        <div class="mt-8 flex justify-center gap-3">
            <x-ui.button variant="secondary" :href="route('marketing.home')">Back to the site</x-ui.button>
        </div>
    </section>
</x-layouts.marketing>
