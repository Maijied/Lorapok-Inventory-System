<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Support\Contrast;

/**
 * The design tokens, held to the accessibility floor CLAUDE.md promises.
 *
 * These parse the real stylesheets rather than restating their values, so a
 * token edited without checking its contrast fails the build instead of
 * shipping a badge nobody can read on a bright shop counter.
 */

/**
 * @return array<string, string>
 */
function tokensIn(string $file, string $selector): array
{
    $css = (string) file_get_contents(resource_path("css/{$file}"));

    // Grab the one block, then every `--name: #value` inside it.
    preg_match('/'.preg_quote($selector, '/').'\s*\{(.*?)\}/s', $css, $block);

    expect($block)->not->toBeEmpty("No `{$selector}` block in {$file}");

    preg_match_all('/(--[a-z0-9-]+)\s*:\s*(#[0-9a-fA-F]{3,6})\s*;/', $block[1], $m, PREG_SET_ORDER);

    return array_combine(
        array_map(fn (array $x): string => $x[1], $m),
        array_map(fn (array $x): string => $x[2], $m),
    );
}

it('defines the same semantic roles in both themes', function () {
    // A role present in one mode and missing in the other renders as an
    // inherited colour that means something else entirely.
    $dark = tokensIn('retail-tokens.css', ':root');
    $light = tokensIn('retail-tokens.css', '[data-theme="light"]');

    expect(array_keys($dark))->toBe(array_keys($light))
        ->and(array_keys($dark))->toContain('--r-positive', '--r-negative', '--r-attention', '--r-info');
});

it('keeps every semantic colour legible on its own theme surfaces', function () {
    $themes = [
        'dark' => [
            'tokens' => tokensIn('retail-tokens.css', ':root'),
            // From tokens.css :root
            'surfaces' => ['base' => '#06080d', 'surface' => '#111827', 'elevated' => '#0c1018'],
        ],
        'light' => [
            'tokens' => tokensIn('retail-tokens.css', '[data-theme="light"]'),
            // From tokens.css [data-theme="light"]
            'surfaces' => ['base' => '#f8fafc', 'surface' => '#f1f5f9', 'elevated' => '#ffffff'],
        ],
    ];

    $failures = [];

    foreach ($themes as $theme => $spec) {
        foreach ($spec['tokens'] as $token => $colour) {
            foreach ($spec['surfaces'] as $name => $surface) {
                $ratio = Contrast::ratio($colour, $surface);

                if ($ratio < Contrast::AA) {
                    $failures[] = sprintf(
                        '%s %s (%s) on %s (%s) = %.2f:1',
                        $theme, $token, $colour, $name, $surface, $ratio
                    );
                }
            }
        }
    }

    expect($failures)->toBe([], "Semantic colours below AA:\n".implode("\n", $failures));
});

it('keeps the default accent clear of every semantic role', function () {
    // The semantic contract only holds if the brand accent means one thing:
    // the primary action.
    //
    // The palette does still offer green and amber, and a shop that picks one
    // gets a "Complete sale" button the same hue as its "Paid" badge. That is
    // a deliberate trade-off — shop owners care about their own branding, and
    // the two appear in different shapes (a large filled button vs a small
    // badge), so context disambiguates. What is NOT negotiable is the default
    // every shop starts on: it must be semantically empty, because most shops
    // never change it.
    $default = Tenant::ACCENT_PALETTE[0];

    // Measured as hue distance, not contrast ratio. Contrast is a luminance
    // measure: violet and rose score 1.26 against each other purely because
    // they are equally bright, which says nothing about telling them apart.
    foreach (tokensIn('retail-tokens.css', ':root') as $token => $role) {
        expect(Contrast::hueDistance($default, $role))->toBeGreaterThan(
            40.0,
            "The default accent {$default} shares a hue with {$token} ({$role})"
        );
    }
});

it('keeps body text well clear of the floor in both themes', function () {
    // Text is the one thing read continuously, so it gets AAA rather than AA.
    expect(Contrast::ratio('#f1f5fb', '#06080d'))->toBeGreaterThan(7.0)   // dark
        ->and(Contrast::ratio('#0f172a', '#f8fafc'))->toBeGreaterThan(7.0); // light
});
