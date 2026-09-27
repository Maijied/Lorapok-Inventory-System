@props(['tone' => 'neutral'])

{{--
    A small status pill.

    Tones come from the semantic contract, so a badge means the same thing as
    a button of the same colour. `neutral` is the default because most status
    is not good or bad — it is simply a fact.
--}}
@php
    $tones = [
        'neutral' => 'border-[var(--color-border)] text-[var(--color-muted)]',
        'positive' => 'border-[var(--r-positive)]/40 text-[var(--r-positive)]',
        'negative' => 'border-[var(--r-negative)]/40 text-[var(--r-negative)]',
        'attention' => 'border-[var(--r-attention)]/40 text-[var(--r-attention)]',
        'info' => 'border-[var(--r-info)]/40 text-[var(--r-info)]',
    ];
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs whitespace-nowrap',
    $tones[$tone] ?? $tones['neutral'],
]) }}>{{ $slot }}</span>
