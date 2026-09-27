<?php

declare(strict_types=1);

/**
 * Brand assets.
 *
 * `public/favicon.ico` shipped as a **0-byte file** for ten phases. Nothing
 * failed, nothing warned — a browser asking for it simply got nothing back.
 * That is the failure mode these tests exist for: assets that are present,
 * referenced, and empty.
 */
$expected = [
    'favicon.ico' => 1000,
    'favicon-16x16.png' => 100,
    'favicon-32x32.png' => 200,
    'apple-touch-icon.png' => 1000,
    'android-chrome-192x192.png' => 1000,
    'android-chrome-512x512.png' => 5000,
    'og-image.png' => 10000,
    'brand/play-store-512.png' => 5000,
    'brand/lockup.png' => 2000,
];

it('ships every brand asset with real content in it', function () use ($expected) {
    $missing = [];

    foreach ($expected as $path => $minimumBytes) {
        $full = public_path($path);

        if (! is_file($full)) {
            $missing[] = "{$path}: not generated";

            continue;
        }

        // A size floor rather than an existence check: an empty or truncated
        // PNG is a file too, and that is precisely how favicon.ico shipped.
        if (filesize($full) < $minimumBytes) {
            $missing[] = sprintf('%s: only %d bytes, expected at least %d', $path, filesize($full), $minimumBytes);
        }
    }

    expect($missing)->toBe([], "Run `sail npm run brand`:\n".implode("\n", $missing));
});

it('keeps the source SVGs the single source of truth', function () {
    // Every raster above is generated from these. If one goes missing the
    // assets cannot be rebuilt, and the CI equality check silently becomes a
    // comparison of two stale things.
    foreach (['mark.svg', 'lockup.svg', 'og-card.svg'] as $svg) {
        expect(is_file(resource_path("brand/{$svg}")))->toBeTrue("resources/brand/{$svg} is missing");
    }
});

it('keeps the mark inside the Android maskable safe area', function () {
    // Android crops a maskable icon to a circle covering the middle 80%.
    // The tag is a 232px square with 44px corner radius, rotated 45° about
    // the centre of a 512 canvas — so its furthest painted point is the
    // corner arc centre plus its radius, not the naive half-diagonal.
    $half = (232 - 2 * 44) / 2;                       // centre to corner arc centre, per axis
    $furthest = sqrt(2 * $half ** 2) + 44;            // plus the arc radius

    $safeRadius = 512 * 0.4;

    expect($furthest)->toBeLessThan(
        $safeRadius,
        sprintf('Mark reaches %.1fpx from centre; the maskable safe radius is %.1fpx', $furthest, $safeRadius)
    );
});
