@props([
    'href',
    'label',
    'icon' => null,
    'active' => false,
])

{{--
    One sidebar destination.

    `aria-current="page"` is what a screen reader announces; the ring and
    background are the sighted equivalent. Both are driven by the same flag so
    they can never disagree.
--}}
<a href="{{ $href }}"
   @if ($active) aria-current="page" @endif
   wire:navigate
   class="group flex items-center gap-3 rounded-[var(--radius)] px-3 py-2.5 text-sm font-medium transition-colors
          {{ $active
              ? 'bg-[color-mix(in_srgb,var(--color-accent)_16%,transparent)] text-[var(--color-text)]'
              : 'text-[var(--color-muted)] hover:bg-[var(--color-bg-surface-hover)] hover:text-[var(--color-text)]' }}">
    @if ($icon)
        <span aria-hidden="true"
              class="shrink-0 {{ $active ? 'text-[var(--color-accent)]' : 'text-[var(--color-muted)] group-hover:text-[var(--color-text)]' }}">
            {!! $icon !!}
        </span>
    @endif

    <span>{{ $label }}</span>
</a>
