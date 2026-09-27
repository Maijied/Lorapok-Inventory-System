@props([
    'variant' => 'primary',
    'size' => 'md',
    'as' => null,
])

{{--
    The one button.

    Before this, the same class string was pasted into nine places, and when
    the accent's text colour turned out to fail WCAG AA it had to be corrected
    in seven files. A shared component means the next such fix is one edit.

    Variants carry meaning, not decoration:

      primary    the main action on the screen — brand accent, one per view
      secondary  an equal alternative
      ghost      a tertiary action that should not compete
      danger     destructive and irreversible: void, delete, suspend
      positive   confirms money in — take payment, mark paid

    `danger` and `positive` draw from the semantic contract in
    retail-tokens.css, so they mean the same thing here as a badge does.
--}}

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-[var(--radius)] font-medium '
        .'transition-opacity disabled:cursor-not-allowed disabled:opacity-50';

    $sizes = [
        'sm' => 'px-3 py-1.5 text-sm',
        'md' => 'px-4 py-2.5 text-sm',
        // Big enough to hit reliably on a touch screen at a busy counter.
        'lg' => 'px-6 py-3.5 text-base',
    ];

    $variants = [
        'primary' => 'bg-[var(--color-accent)] text-[var(--color-on-accent)] hover:opacity-90',
        'secondary' => 'border border-[var(--color-border)] text-[var(--color-text)] '
            .'hover:bg-[var(--color-bg-surface-hover)]',
        'ghost' => 'text-[var(--color-muted)] hover:bg-[var(--color-bg-surface-hover)] '
            .'hover:text-[var(--color-text)]',
        'danger' => 'bg-[var(--r-negative)] text-white hover:opacity-90',
        'positive' => 'bg-[var(--r-positive)] text-white hover:opacity-90',
    ];

    $classes = trim($base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']));

    // An anchor when it navigates, a button when it acts. Getting this wrong
    // costs keyboard and screen-reader users the right affordance.
    $tag = $as ?? ($attributes->has('href') ? 'a' : 'button');
@endphp

<{{ $tag }}
    @if ($tag === 'button' && ! $attributes->has('type')) type="button" @endif
    {{ $attributes->class($classes) }}>{{ $slot }}</{{ $tag }}>
