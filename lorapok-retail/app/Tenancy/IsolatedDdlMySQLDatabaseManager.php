<?php

declare(strict_types=1);

namespace App\Tenancy;

use Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager;

/**
 * Provisions tenant databases over a connection of their own.
 *
 * stancl points its database manager at the central connection, so
 * `CREATE DATABASE` and `DROP DATABASE` are issued on the same PDO handle
 * that `RefreshDatabase` has a test transaction open on. Both statements
 * implicitly commit in MySQL, which ends that transaction early; Laravel
 * notices (`RefreshDatabaseState::$migrated` flips to false) and rebuilds the
 * whole central schema before the next test.
 *
 * Measured on this suite before the fix: `CatalogTest` ran 14 tests and
 * `migrate:fresh` ran 14 times. Same server, same credentials, different
 * handle — the implicit commit then has nothing of ours to commit.
 *
 * This matters in production too, for the same reason: provisioning a shop
 * inside a transaction would silently commit it.
 */
class IsolatedDdlMySQLDatabaseManager extends MySQLDatabaseManager
{
    public function setConnection(string $connection): void
    {
        parent::setConnection(
            config()->has('database.connections.tenancy_ddl') ? 'tenancy_ddl' : $connection
        );
    }
}
