<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;

/**
 * The shared UI components.
 *
 * These render for real rather than asserting on their source, because what
 * matters is the markup that reaches a browser — an `aria-describedby` that
 * points at an id the template never emits looks correct in a diff and is
 * useless to a screen reader.
 */
function render(string $template, array $data = []): string
{
    // $errors is normally supplied by the ShareErrorsFromSession middleware,
    // which does not run here. Passing it as render data is not enough — it
    // would stay in the outer scope and never reach a nested component — so
    // it is shared with every view instead.
    View::share('errors', new ViewErrorBag);

    return Blade::render($template, $data);
}

it('renders a plain button by default', function () {
    $html = render('<x-ui.button>Save</x-ui.button>');

    expect($html)->toContain('<button')
        // Defaults to type=button: inside a form, an untyped button submits it,
        // which has rung up sales by accident in systems like this.
        ->and($html)->toContain('type="button"');
});

it('renders an anchor when given an href', function () {
    $html = render('<x-ui.button href="/pos">Sell</x-ui.button>');

    expect($html)->toContain('<a')
        ->and($html)->toContain('href="/pos"')
        ->and($html)->not->toContain('type="button"');
});

it('lets a call site pass through Livewire attributes', function () {
    $html = render('<x-ui.button type="submit" wire:loading.attr="disabled">Go</x-ui.button>');

    expect($html)->toContain('type="submit"')
        ->and($html)->toContain('wire:loading.attr="disabled"');
});

it('paints the primary button with the computed on-accent colour', function () {
    // Never a hardcoded white: which text colour survives on a shop's accent
    // is a per-shop question, and getting it wrong put nine buttons below AA.
    expect(render('<x-ui.button>Pay</x-ui.button>'))
        ->toContain('text-[var(--color-on-accent)]')
        ->not->toContain('text-white');
});

it('ties a field label, input and hint together by id', function () {
    $html = render('<x-ui.field label="Email" name="email" type="email" hint="Work address" />');

    expect($html)->toContain('for="email"')
        ->and($html)->toContain('id="email"')
        ->and($html)->toContain('type="email"')
        ->and($html)->toContain('Work address');
});

it('marks a required field for both sighted and screen reader users', function () {
    $html = render('<x-ui.field label="SKU" name="sku" :required="true" />');

    // The asterisk is decorative and hidden; the text is what gets announced.
    expect($html)->toContain('aria-hidden="true"')
        ->and($html)->toContain('(required)')
        ->and($html)->toContain('required');
});

it('interrupts for a failure but not for a confirmation', function () {
    // role=alert preempts whatever a screen reader is saying. Correct for an
    // error, rude for a success message.
    expect(render('<x-ui.alert tone="negative">Sale failed</x-ui.alert>'))
        ->toContain('role="alert"')
        ->and(render('<x-ui.alert tone="positive">Saved</x-ui.alert>'))
        ->toContain('role="status"');
});

it('draws badge tones from the semantic contract', function () {
    // A badge and a button of the same colour must mean the same thing.
    expect(render('<x-ui.badge tone="positive">Paid</x-ui.badge>'))
        ->toContain('--r-positive')
        ->and(render('<x-ui.badge tone="negative">Voided</x-ui.badge>'))
        ->toContain('--r-negative')
        ->and(render('<x-ui.badge>Draft</x-ui.badge>'))
        ->toContain('--color-muted');
});

it('lets an empty state offer the next step', function () {
    $html = render(
        '<x-ui.empty-state title="No products yet" description="Add your first one.">'
        .'<x-slot:action><x-ui.button href="/products/create">Add a product</x-ui.button></x-slot:action>'
        .'</x-ui.empty-state>'
    );

    expect($html)->toContain('No products yet')
        ->and($html)->toContain('Add your first one.')
        // An empty screen that dead-ends is where a new shop gives up.
        ->and($html)->toContain('href="/products/create"');
});
