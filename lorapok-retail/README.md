# Lorapok Retail — application

The Laravel application. For the project overview, architecture and quick start,
see the [repository README](../README.md). For engineering conventions — the
load-bearing ones — see [CLAUDE.md](CLAUDE.md).

## Running it

**Never run `php` or `artisan` on the host.** The host PHP has `PDO` but zero
PDO drivers, so anything touching the database fails with "could not find
driver". Everything goes through Sail:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan …
./vendor/bin/sail pest
./vendor/bin/sail npm run dev
```

## Commands worth knowing

| Command | What it does |
|---|---|
| `artisan stock:reconcile` | Proves `stock_levels` matches the `stock_movements` ledger. Exits non-zero on drift. |
| `artisan metrics:rollup` | Aggregates each shop's day into the central `tenant_daily_metrics` table, so cross-shop reporting never fans out across databases. |

## Checks

```bash
./vendor/bin/sail artisan test      # full suite (provisions real tenant databases)
./vendor/bin/sail pest tests/Unit   # architecture + money, no database, ~7s
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
```

Pint and PHPStan run on the host — they are the only two things that can, since
neither touches a database.

## Where things live

- `app/Domain/` — business rules. No HTTP, no Blade. `StockService::apply()` is
  the only thing that writes a stock movement.
- `app/Models/Tenant/` — per-shop models. Everything here is covered by the
  generic isolation test in `tests/Feature/TenancyIsolationTest.php`.
- `database/migrations/` — central database. `database/migrations/tenant/` — per
  shop. No data backfills in tenant migrations; they run once per tenant on
  every deploy, so backfills belong in queued jobs.
- `resources/views/components/` — Blade and Livewire. A `⚡` filename prefix
  marks a Livewire single-file component; the prefix is not part of its name.
