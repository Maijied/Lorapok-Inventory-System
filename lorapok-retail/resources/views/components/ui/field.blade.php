@props([
    'label',
    'name',
    'type' => 'text',
    'hint' => null,
    'required' => false,
])

{{--
    A labelled input with its error.

    The three parts have to move together or accessibility quietly breaks: the
    label needs the input's id, and the error needs to be referenced by
    aria-describedby *and* announced. Hand-written, one of the three gets
    forgotten. Built once, it cannot.
--}}
@php
    $id = $attributes->get('id', $name);
    $errorId = "{$id}-error";
@endphp

<div>
    <label for="{{ $id }}" class="mb-1.5 block text-sm font-medium text-[var(--color-text)]">
        {{ $label }}
        @if ($required)
            <span class="text-[var(--r-negative)]" aria-hidden="true">*</span>
            <span class="sr-only">(required)</span>
        @endif
    </label>

    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($required) required @endif
        @error($name) aria-invalid="true" aria-describedby="{{ $errorId }}" @enderror
        {{ $attributes->class([
            'w-full rounded-[var(--radius)] border bg-[var(--color-bg-base)]/60 px-3 py-2',
            'text-[var(--color-text)] placeholder:text-[var(--color-muted)]',
            'focus:outline-none focus:border-[var(--color-accent)]',
            'border-[var(--color-border)]' => ! $errors->has($name),
            // A red ring alone would be invisible to anyone who cannot
            // distinguish it; the message below carries the same information.
            'border-[var(--r-negative)]' => $errors->has($name),
        ]) }}
    >

    @error($name)
        <p id="{{ $errorId }}" class="mt-1.5 text-sm text-[var(--r-negative)]">{{ $message }}</p>
    @else
        @if ($hint)
            <p class="mt-1.5 text-xs text-[var(--color-muted)]">{{ $hint }}</p>
        @endif
    @enderror
</div>
