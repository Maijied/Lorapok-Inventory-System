@props(['title', 'description' => null])

{{--
    What a screen says when it has nothing to show.

    An empty table is ambiguous — nothing here yet, or nothing matched, or
    something failed? Saying which, and offering the next step, is the whole
    point. Empty states are also where a new shop spends its first ten minutes.
--}}
<div {{ $attributes->class('flex flex-col items-center justify-center px-6 py-12 text-center') }}>
    <p class="text-sm font-medium text-[var(--color-text)]">{{ $title }}</p>

    @if ($description)
        <p class="mt-1.5 max-w-sm text-sm text-[var(--color-muted)]">{{ $description }}</p>
    @endif

    @isset($action)
        <div class="mt-5">{{ $action }}</div>
    @endisset
</div>
