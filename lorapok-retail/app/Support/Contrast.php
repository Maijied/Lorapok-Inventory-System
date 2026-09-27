<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * WCAG 2.1 relative luminance and contrast.
 *
 * Every shop picks its own accent colour, and that accent is used as a button
 * fill. Which text colour stays legible on it is therefore a per-shop question
 * that cannot be answered by a static stylesheet — CSS has no luminance
 * function. So it is answered here, at render time, and injected as
 * `--color-on-accent`.
 *
 * This exists because the shipped default accent `#7c5cff` carried white text
 * at 4.35:1 and near-black at 4.47:1 — below the 4.5:1 AA floor either way. It
 * sat in the gap where neither choice is legible, and nothing in the codebase
 * could have noticed.
 */
final class Contrast
{
    /** Text colour used on light fills. Matches --color-text in light mode. */
    public const INK = '#0f172a';

    /** Text colour used on dark fills. */
    public const PAPER = '#ffffff';

    /** WCAG AA for normal-sized text. */
    public const AA = 4.5;

    /** WCAG AA for large text (>=18.66px bold or >=24px). */
    public const AA_LARGE = 3.0;

    /**
     * Relative luminance of a `#rrggbb` colour, per WCAG 2.1.
     */
    public static function luminance(string $hex): float
    {
        [$r, $g, $b] = self::channels($hex);

        return 0.2126 * self::linearise($r)
            + 0.7152 * self::linearise($g)
            + 0.0722 * self::linearise($b);
    }

    /**
     * Contrast ratio between two colours, from 1.0 (identical) to 21.0.
     */
    public static function ratio(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * The more legible of ink or paper on the given fill.
     *
     * Always returns one of the two, even when neither reaches AA — a button
     * still has to render. Use `isLegible()` to find out whether the choice is
     * actually good enough; `Tenant::ACCENT_PALETTE` is covered by a test that
     * asserts it for every entry.
     */
    public static function onColor(string $fill): string
    {
        return self::ratio(self::INK, $fill) >= self::ratio(self::PAPER, $fill)
            ? self::INK
            : self::PAPER;
    }

    /**
     * Whether the best available text colour on this fill reaches AA.
     */
    public static function isLegible(string $fill, float $threshold = self::AA): bool
    {
        return self::ratio(self::onColor($fill), $fill) >= $threshold;
    }

    /**
     * Hue angle in degrees, 0-360.
     *
     * Contrast ratio is a *luminance* measure, so two colours can score 1.26
     * against each other and still be violet and rose — completely different
     * to look at. When the question is "can someone tell these apart", hue is
     * the axis that answers it.
     */
    public static function hue(string $hex): float
    {
        [$r, $g, $b] = array_map(fn (int $c): float => $c / 255, self::channels($hex));

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $delta = $max - $min;

        if ($delta === 0.0) {
            return 0.0;   // grey has no hue
        }

        $hue = match (true) {
            $max === $r => 60 * fmod(($g - $b) / $delta, 6),
            $max === $g => 60 * ((($b - $r) / $delta) + 2),
            default => 60 * ((($r - $g) / $delta) + 4),
        };

        return fmod($hue + 360, 360);
    }

    /**
     * Shortest distance between two hues on the colour wheel, 0-180 degrees.
     */
    public static function hueDistance(string $a, string $b): float
    {
        $diff = abs(self::hue($a) - self::hue($b));

        return min($diff, 360 - $diff);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function channels(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');

        // Accept the 3-digit shorthand so a token file can use either form.
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (preg_match('/^[0-9a-f]{6}$/i', $hex) !== 1) {
            throw new InvalidArgumentException("Not a hex colour: {$hex}");
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /** sRGB channel to linear light. */
    private static function linearise(int $channel): float
    {
        $c = $channel / 255;

        return $c <= 0.04045
            ? $c / 12.92
            : (($c + 0.055) / 1.055) ** 2.4;
    }
}
