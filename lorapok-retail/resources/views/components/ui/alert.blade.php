@props(['tone' => 'info', 'title' => null])

{{--
    An inline message about what just happened.

    `role` is chosen by tone, not decoration: a failure has to interrupt a
    screen reader (`alert`), while a confirmation should wait its turn
    (`status`). Getting that backwards either talks over someone mid-task or
    silently swallows an error.
--}}
@php
    $tones = [
        'info' => ['border-[var(--r-info)]/40', 'text-[var(--r-info)]'],
        'positive' => ['border-[var(--r-positive)]/40', 'text-[var(--r-positive)]'],
        'attention' => ['border-[var(--r-attention)]/40', 'text-[var(--r-attention)]'],
        'negative' => ['border-[var(--r-negative)]/40', 'text-[var(--r-negative)]'],
    ];

    [$border, $accent] = $tones[$tone] ?? $tones['info'];
@endphp

<div role="{{ $tone === 'negative' ? 'alert' : 'status' }}"
     {{ $attributes->class(['rounded-[var(--radius)] border bg-[var(--color-bg-surface)]/60 px-4 py-3', $border]) }}>
    @if ($title)
        <p class="text-sm font-medium {{ $accent }}">{{ $title }}</p>
    @endif

    <div class="{{ $title ? 'mt-1 ' : '' }}text-sm text-[var(--color-text)]">{{ $slot }}</div>
</div>
