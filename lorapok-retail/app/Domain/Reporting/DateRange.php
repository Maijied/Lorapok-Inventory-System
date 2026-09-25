<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use Carbon\CarbonImmutable;
use DomainException;

/**
 * A reporting window.
 *
 * Bounds are inclusive of whole days in the shop's own timezone, because a
 * shop owner asking for "today" means their trading day, not UTC.
 */
final readonly class DateRange
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {
        if ($start->greaterThan($end)) {
            throw new DomainException('A report cannot start after it ends.');
        }
    }

    public static function of(string $from, string $to, ?string $timezone = null): self
    {
        $tz = $timezone ?? 'UTC';

        return new self(
            CarbonImmutable::parse($from, $tz)->startOfDay(),
            CarbonImmutable::parse($to, $tz)->endOfDay(),
        );
    }

    public static function today(?string $timezone = null): self
    {
        $now = CarbonImmutable::now($timezone ?? 'UTC');

        return new self($now->startOfDay(), $now->endOfDay());
    }

    public static function last(int $days, ?string $timezone = null): self
    {
        $now = CarbonImmutable::now($timezone ?? 'UTC');

        return new self($now->subDays($days - 1)->startOfDay(), $now->endOfDay());
    }

    public static function thisMonth(?string $timezone = null): self
    {
        $now = CarbonImmutable::now($timezone ?? 'UTC');

        return new self($now->startOfMonth(), $now->endOfMonth());
    }

    public function days(): int
    {
        return (int) $this->start->diffInDays($this->end) + 1;
    }
}
