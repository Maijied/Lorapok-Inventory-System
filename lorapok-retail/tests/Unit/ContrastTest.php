<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Support\Contrast;

/**
 * Colour legibility.
 *
 * A till is read at a glance, often over a shoulder, under shop lighting. A
 * button whose label is barely distinguishable from its fill is not a polish
 * problem — it is a mis-tap on a screen that takes money.
 */
it('computes WCAG luminance at the reference points', function () {
    // The two anchors of the scale: black is 0, white is 1.
    expect(Contrast::luminance('#000000'))->toBe(0.0)
        ->and(Contrast::luminance('#ffffff'))->toBe(1.0);
});

it('computes the WCAG contrast ratio', function () {
    // Black on white is the maximum possible ratio, 21:1.
    expect(round(Contrast::ratio('#000000', '#ffffff'), 2))->toBe(21.0)
        ->and(Contrast::ratio('#123456', '#123456'))->toBe(1.0);
});

it('is symmetric, because contrast has no direction', function () {
    expect(Contrast::ratio('#7c5cff', '#ffffff'))
        ->toBe(Contrast::ratio('#ffffff', '#7c5cff'));
});

it('accepts the three-digit shorthand', function () {
    expect(Contrast::ratio('#fff', '#000'))->toBe(Contrast::ratio('#ffffff', '#000000'));
});

it('rejects anything that is not a colour', function () {
    // The accent arrives from the database. A malformed value must throw here
    // rather than silently produce a luminance and a plausible-looking button.
    expect(fn () => Contrast::luminance('red'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Contrast::luminance('#gg0000'))->toThrow(InvalidArgumentException::class);
});

it('picks the more legible text colour for a fill', function () {
    expect(Contrast::onColor('#fbbf24'))->toBe(Contrast::INK)      // amber: dark text
        ->and(Contrast::onColor('#1e293b'))->toBe(Contrast::PAPER); // slate: light text
});

it('gives every shop accent a legible label', function () {
    // Each shop picks from this palette and it becomes a button fill on every
    // screen. One entry that cannot carry text makes that shop's primary
    // action illegible — and nothing else in the codebase would notice.
    $failures = [];

    foreach (Tenant::ACCENT_PALETTE as $accent) {
        $on = Contrast::onColor($accent);
        $ratio = Contrast::ratio($on, $accent);

        if ($ratio < Contrast::AA) {
            $failures[] = sprintf(
                '%s: best text is %s at only %.2f:1 (needs %.1f)',
                $accent, $on, $ratio, Contrast::AA
            );
        }
    }

    expect($failures)->toBe([], "Accent colours no text is legible on:\n".implode("\n", $failures));
});

it('keeps every shop accent readable as text on the dark surface', function () {
    // The accent is used two ways: as a button fill with a label on it, and as
    // coloured text on a panel (active nav, links, the margin figure). A colour
    // can pass the first and fail the second — '#6d4aff' reaches 5.15:1 as a
    // fill and only 3.77:1 as text — so both roles are checked.
    $failures = [];

    foreach (Tenant::ACCENT_PALETTE as $accent) {
        $ratio = Contrast::ratio($accent, '#0b0d12');

        if ($ratio < Contrast::AA) {
            $failures[] = sprintf('%s: %.2f:1 as text on the dark surface', $accent, $ratio);
        }
    }

    expect($failures)->toBe([], "Accents unreadable as text:\n".implode("\n", $failures));
});

it('keeps the shop accents visually distinct from one another', function () {
    // Two accents a shop owner cannot tell apart make the palette a list of
    // near-duplicates rather than a choice.
    $palette = Tenant::ACCENT_PALETTE;

    expect($palette)->toHaveCount(count(array_unique($palette)));
});
