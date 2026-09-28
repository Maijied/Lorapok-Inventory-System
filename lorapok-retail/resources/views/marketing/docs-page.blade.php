<x-layouts.marketing :seo="$seo">
    <article class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
        <a href="{{ route('marketing.docs') }}"
           class="text-sm text-[var(--color-muted)] hover:text-[var(--color-text)]">← Handbook</a>

        {{-- Ours, written in this repository, never user-supplied. --}}
        <div class="help-prose mt-6">{!! $body !!}</div>
    </article>
</x-layouts.marketing>
