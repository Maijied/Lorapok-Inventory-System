<?php

declare(strict_types=1);

use App\Support\Money;

it('holds amounts as integer minor units', function () {
    expect(Money::parse('1250.50')->minor)->toBe(125050)
        ->and(Money::parse('0.01')->minor)->toBe(1)
        ->and(Money::ofMinor(125050)->toDecimal())->toBe('1250.50');
});

it('accepts human formatting with separators', function () {
    expect(Money::parse('1,250.50')->minor)->toBe(125050)
        ->and(Money::parse(' 99 ')->minor)->toBe(9900);
});

it('rejects input that is not a number', function () {
    expect(fn () => Money::parse('abc'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Money::parse(''))->toThrow(InvalidArgumentException::class);
});

it('adds without the rounding drift floats produce', function () {
    // 0.1 + 0.2 !== 0.3 in binary floating point. Summing line totals as
    // floats is how an invoice ends up a paisa out and stops reconciling.
    $total = Money::parse('0.10')->plus(Money::parse('0.20'));

    expect($total->minor)->toBe(30)
        ->and($total->toDecimal())->toBe('0.30');
});

it('sums many small amounts exactly', function () {
    $total = Money::ofMinor(0);

    foreach (range(1, 1000) as $ignored) {
        $total = $total->plus(Money::parse('0.07'));
    }

    expect($total->minor)->toBe(7000)
        ->and($total->toDecimal())->toBe('70.00');
});

it('multiplies by a quantity', function () {
    expect(Money::parse('19.99')->times(3)->toDecimal())->toBe('59.97');
});

it('applies a tax rate given in basis points', function () {
    // 1850 bps = 18.50%
    expect(Money::parse('100.00')->percentageBps(1850)->toDecimal())->toBe('18.50')
        ->and(Money::parse('0.00')->percentageBps(1850)->isZero())->toBeTrue();
});

it('refuses to combine different currencies', function () {
    // Silently summing across currencies is the classic way a multi-currency
    // total becomes quietly wrong.
    expect(fn () => Money::ofMinor(100, 'BDT')->plus(Money::ofMinor(100, 'USD')))
        ->toThrow(InvalidArgumentException::class);
});

it('can represent a negative amount for refunds', function () {
    $refund = Money::parse('10.00')->minus(Money::parse('25.00'));

    expect($refund->isNegative())->toBeTrue()
        ->and($refund->toDecimal())->toBe('-15.00');
});

it('formats for display', function () {
    expect(Money::parse('1250.5')->format('৳'))->toBe('৳ 1,250.50');
});
