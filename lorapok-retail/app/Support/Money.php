<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * An amount of money, held as an integer count of minor units.
 *
 * Floats cannot represent 0.1 exactly, so accumulating them across sale lines
 * produces totals that are a paisa out and invoices that do not reconcile.
 * Every monetary column in this application is a BIGINT of minor units and
 * passes through here.
 */
final readonly class Money
{
    private function __construct(
        public int $minor,
        public string $currency,
    ) {}

    public static function ofMinor(int $minor, string $currency = 'BDT'): self
    {
        return new self($minor, strtoupper($currency));
    }

    /**
     * Parse human input such as "1,250.50".
     *
     * Rounds half-up at the minor unit; anything with more precision than the
     * currency supports is a data-entry error, not something to silently keep.
     */
    public static function parse(string $amount, string $currency = 'BDT'): self
    {
        $normalised = str_replace([',', ' '], '', trim($amount));

        if ($normalised === '' || ! is_numeric($normalised)) {
            throw new InvalidArgumentException("Not a valid amount: {$amount}");
        }

        return new self((int) round((float) $normalised * 100), strtoupper($currency));
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    /** Multiply by a quantity, rounding half-up to the minor unit. */
    public function times(int|float $multiplier): self
    {
        return new self((int) round($this->minor * $multiplier), $this->currency);
    }

    /** Apply a rate given in basis points (1850 = 18.50%). */
    public function percentageBps(int $bps): self
    {
        return new self((int) round($this->minor * $bps / 10_000), $this->currency);
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    /** Decimal string for display, e.g. "1250.50". */
    public function toDecimal(): string
    {
        return number_format($this->minor / 100, 2, '.', '');
    }

    public function format(?string $symbol = null): string
    {
        return trim(($symbol ?? $this->currency).' '.number_format($this->minor / 100, 2));
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            // Silently adding across currencies is the classic way a
            // multi-currency total becomes quietly wrong.
            throw new InvalidArgumentException(
                "Cannot combine {$this->currency} with {$other->currency}",
            );
        }
    }
}
