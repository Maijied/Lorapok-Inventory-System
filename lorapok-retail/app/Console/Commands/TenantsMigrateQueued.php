<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Stancl\Tenancy\Jobs\MigrateDatabase;

/**
 * Migrate every shop's database, one queued job per shop.
 *
 * `tenants:migrate` runs them inline. That is fine for four shops and wrong
 * for fifty: a deploy would sit blocked for minutes with the previous image
 * still serving, and one shop whose schema is mid-migration stalls every shop
 * behind it in the queue.
 *
 * Dispatching per shop means a slow or failing one is isolated — it lands in
 * `failed_jobs` with its tenant id, and the rest still migrate.
 */
final class TenantsMigrateQueued extends Command
{
    protected $signature = 'tenants:migrate-queued
                            {--sync : Run inline instead of queueing, for local use}';

    protected $description = "Queue a migration job for every shop's database";

    public function handle(): int
    {
        // Larastan cannot infer the model type through stancl's base Tenant,
        // so it is stated here — the same annotation StockReconcile and
        // MetricsRollupCommand already carry for the same reason.
        /** @var Collection<int, Tenant> $tenants */
        $tenants = Tenant::query()->get();

        if ($tenants->isEmpty()) {
            $this->info('No shops to migrate.');

            return self::SUCCESS;
        }

        $sync = (bool) $this->option('sync');

        foreach ($tenants as $tenant) {
            $job = new MigrateDatabase($tenant);

            $sync ? dispatch_sync($job) : dispatch($job);

            $this->line(sprintf('  %s %s', $sync ? 'migrated' : 'queued', $tenant->slug));
        }

        $this->info(sprintf(
            '%d shop database%s %s.',
            $tenants->count(),
            $tenants->count() === 1 ? '' : 's',
            $sync ? 'migrated' : 'queued for migration',
        ));

        return self::SUCCESS;
    }
}
