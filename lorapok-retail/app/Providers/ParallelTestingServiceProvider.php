<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Makes the tenancy layer safe to run in several processes at once.
 *
 * Laravel already gives each parallel process its own CENTRAL database
 * (`testing_test_1`, `_2`, …). Tenant databases sit outside that: stancl
 * creates real schemas named from `config('tenancy.database.prefix')`, which is
 * a single global value. Without this, every process would provision into one
 * shared namespace and one process's teardown sweep would drop another
 * process's live schema — presenting as unreproducible flake.
 */
class ParallelTestingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! $this->app->runningInConsole() || ! $this->app->runningUnitTests()) {
            return;
        }

        // Illuminate's own ParallelTestingServiceProvider is deferred.
        // Resolving the facade mid-boot queues its boot() onto a callback list
        // that has already drained, so it silently never runs — and the
        // per-process central database switch never happens. Waiting for
        // booted() makes the registration take effect immediately instead.
        $this->app->booted(fn () => $this->registerCallbacks());
    }

    private function registerCallbacks(): void
    {
        /*
         * Runs inside the worker, per test, after refreshApplication() and
         * before the traits are set up.
         *
         * It has to be per-test: refreshApplication() rebuilds the config
         * repository, so a value set once would be discarded. And it cannot be
         * setUpProcess() — that runs in the PARENT against a throwaway
         * application which is flushed immediately, so nothing it writes to
         * config ever reaches a worker.
         *
         * DatabaseConfig's name generator reads this key at call time, and the
         * resolved name is then persisted onto the tenant row, so a worker can
         * never mis-resolve a tenant it created.
         *
         * The literal `tenant_` head is load-bearing: the MySQL grant is
         * `tenant\_%`, where `\_` is a literal underscore and `%` is the rest.
         * `tenant_p1_…` matches; `tenantp1_…` would not, and provisioning
         * would fail with an access-denied error that reads like bad
         * credentials.
         */
        ParallelTesting::setUpTestCase(function ($testCase, $token): void {
            config(['tenancy.database.prefix' => "tenant_p{$token}_"]);
        });

        // These run in the parent process, once per token, with a real booted
        // application — no worker is running yet, or any more. That makes them
        // the only place a whole-namespace sweep is safe.
        ParallelTesting::setUpProcess(function ($token): void {
            if (ParallelTesting::option('recreate_databases')) {
                $this->dropTenantDatabases("tenant_p{$token}_");
            }
        });

        ParallelTesting::tearDownProcess(function ($token): void {
            $this->dropTenantDatabases("tenant_p{$token}_");
        });
    }

    /**
     * Drop every tenant schema belonging to one process.
     *
     * The prefix is escaped before it reaches LIKE: an unescaped underscore is
     * a single-character wildcard, which would widen the sweep past this
     * process and into another worker's live databases.
     */
    private function dropTenantDatabases(string $prefix): void
    {
        $connection = config()->has('database.connections.tenancy_ddl')
            ? 'tenancy_ddl'
            : config('tenancy.database.central_connection');

        try {
            $pattern = str_replace(['\\', '_', '%'], ['\\\\', '\_', '\%'], $prefix).'%';

            $names = DB::connection($connection)->select(
                'SELECT SCHEMA_NAME AS name FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME LIKE ?',
                [$pattern],
            );

            foreach ($names as $row) {
                DB::connection($connection)->statement("DROP DATABASE `{$row->name}`");
            }
        } catch (Throwable $e) {
            // A safety net, not the primary cleanup path. Failing here must
            // never mask the test result it runs alongside.
            fwrite(STDERR, "tenant sweep for {$prefix} failed: {$e->getMessage()}\n");
        }
    }
}
