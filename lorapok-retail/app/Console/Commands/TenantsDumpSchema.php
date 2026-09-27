<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Regenerate `database/schema/tenant-schema.sql`.
 *
 * Provisioning a shop used to run ten migrations building 37 tables, one
 * statement at a time with schema introspection between each. Loading a single
 * dump instead took that from 70s to 21s per shop locally — and roughly 200
 * tests provision one, so it is the difference between a suite you run and a
 * suite you avoid.
 *
 * A stale dump is slow, not wrong: Laravel records the migrations contained in
 * it, then runs anything newer on top. So forgetting to re-run this costs
 * speed and never correctness — which is the right way round.
 */
final class TenantsDumpSchema extends Command
{
    protected $signature = 'tenants:dump-schema';

    protected $description = 'Regenerate the tenant schema dump used to provision new shops';

    public function handle(): int
    {
        // Built from a throwaway shop rather than a real one, so the dump can
        // never capture a customer's data by accident.
        $tenant = Tenant::create([
            'name' => 'Schema Source',
            'slug' => 'schema-source-'.bin2hex(random_bytes(4)),
        ]);

        try {
            tenancy()->initialize($tenant);

            Artisan::call('schema:dump', [
                '--database' => 'tenant',
                '--path' => database_path('schema/tenant-schema.sql'),
            ]);

            tenancy()->end();

            $this->info('Wrote database/schema/tenant-schema.sql');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Could not dump the tenant schema: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            // Always cleaned up, including on failure — a stray shop named
            // schema-source would otherwise appear in the operator panel.
            tenancy()->end();

            try {
                $tenant->database()->manager()->deleteDatabase($tenant);
            } catch (Throwable) {
                // Never created, or already gone.
            }

            Tenant::withoutEvents(fn () => $tenant->delete());
        }
    }
}
