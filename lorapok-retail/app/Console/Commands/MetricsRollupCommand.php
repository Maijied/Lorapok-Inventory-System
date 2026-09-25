<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Central\MetricsRollup;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Refresh the cross-shop metrics the super-admin dashboard reads.
 *
 * Scheduled hourly in production: recomputing today keeps the dashboard
 * near-live, and recomputing yesterday catches sales voided or returned
 * after midnight.
 */
class MetricsRollupCommand extends Command
{
    protected $signature = 'metrics:rollup
                            {--tenant= : Roll up a single shop by slug}
                            {--from= : Start date (YYYY-MM-DD) for a backfill}
                            {--to= : End date (YYYY-MM-DD) for a backfill}';

    protected $description = 'Aggregate each shop into the central metrics table';

    public function handle(MetricsRollup $rollup): int
    {
        /** @var Collection<int, Tenant> $tenants */
        $tenants = $this->option('tenant')
            ? Tenant::query()->where('slug', $this->option('tenant'))->get()
            : Tenant::query()->get();

        if ($tenants->isEmpty()) {
            $this->error('No matching shops.');

            return self::FAILURE;
        }

        $days = $this->days();
        $written = 0;

        foreach ($tenants as $tenant) {
            foreach ($days as $day) {
                $rollup->forTenant($tenant, $day);
                $written++;
            }
        }

        $this->info(sprintf(
            'Rolled up %d shop-day%s across %d shop%s.',
            $written, $written === 1 ? '' : 's',
            $tenants->count(), $tenants->count() === 1 ? '' : 's',
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<int, CarbonImmutable>
     */
    private function days(): array
    {
        $from = $this->option('from');
        $to = $this->option('to');

        if (! $from) {
            // Today, plus yesterday to catch late corrections.
            $today = CarbonImmutable::now();

            return [$today, $today->subDay()];
        }

        $start = CarbonImmutable::parse($from);
        $end = $to ? CarbonImmutable::parse($to) : $start;

        $days = [];

        for ($day = $start; $day->lessThanOrEqualTo($end); $day = $day->addDay()) {
            $days[] = $day;
        }

        return $days;
    }
}
