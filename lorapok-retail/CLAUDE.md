# Lorapok Retail

Multi-tenant inventory & POS SaaS. A product of Lorapok Labs.
Super admin provisions shops; each shop gets its own subdomain, its own branding
and **its own database**.

`lorapok.tech` · shops at `<shop>.lorapok.tech`
Dev: `lorapok.localhost` · shops at `<shop>.lorapok.localhost`

Shops sit on the apex, not under `retail.`, because Cloudflare's free Universal
SSL covers `*.lorapok.tech` but not a second-level wildcard. That means every
new shop gets HTTPS instantly at no cost — and that `Tenant::RESERVED_SLUGS`
must cover every other Lorapok subdomain.

## Running anything

**Never run `php` or `artisan` on the host.** The host PHP has `PDO` but *zero*
PDO drivers — no `pdo_mysql`, no `pdo_sqlite` — plus no `gd`, `bcmath` or
`redis`. Anything touching the database will fail with "could not find driver".

```
./vendor/bin/sail up -d
./vendor/bin/sail artisan …
./vendor/bin/sail pest
./vendor/bin/sail npm run dev

# Pint and PHPStan also go through Sail now. Horizon added ext-pcntl to the
# platform requirements, and the host PHP does not have it — running them
# directly fails in vendor/composer/platform_check.php.
./vendor/bin/sail php vendor/bin/pint
./vendor/bin/sail php vendor/bin/phpstan analyse
```

Docker Desktop must be running (`systemctl --user start docker-desktop`).

### `vendor/` and `node_modules/` live in Docker volumes, not on the host

This project sits on `/mnt/NewVolume`, which is **NTFS over FUSE**. Every file
operation inside a bind mount crosses that driver, and PHP's autoloader does
thousands per process boot. Measured on the same 11,330 files:

| | |
|---|---|
| stat `vendor/` over the bind mount | **3.84 s** |
| stat `vendor/` in a named volume | **0.39 s** |

So `compose.yaml` mounts both directories as named volumes. Neither is ever
edited by hand, so nothing is lost by keeping them off the host.

**What this means in practice.** A fresh clone, or anything that changes
dependencies, installs *inside the container*:

```
./vendor/bin/sail composer install
./vendor/bin/sail npm ci
```

The host keeps its own `vendor/` — `./vendor/bin/sail` is a host script and
needs it — so the two can drift. If a package behaves oddly, re-run the two
commands above before suspecting the package: the container's copy is the one
that actually runs.

### Provisioning loads a schema dump, not ten migrations

`database/schema/tenant-schema.sql` is loaded when a shop's database is
created, instead of running the ten tenant migrations that build its 37
tables. Locally that took provisioning from **70.7 s to 21.2 s**, and roughly
200 tests provision a shop.

A stale dump is **slow, not wrong**: it records which migrations it contains,
and Laravel runs anything newer on top. Refresh it after adding a tenant
migration:

```
./vendor/bin/sail artisan tenants:dump-schema
```

**Never edit a mounted file with `sed -i`** (or anything else that writes a temp
file and renames over the target). The rename replaces the inode and the
container's bind mount stops resolving the path until it restarts — the symptom
is `Failed to open stream: No such file or directory` for a file that plainly
exists on the host. Write in place instead: `python3 - <<'PY'` with
`Path(...).write_text(...)`, or a heredoc `cat > file`.

## Stack

Laravel 13 · Livewire 4 · Tailwind v4 (CSS-first) · MySQL 8.4 · Redis · Pest 4
Tenancy: `stancl/tenancy ^3.10`, **multi-database mode**.

## Non-negotiables

**Money is never a float.** Store integer minor units in `*_minor` BIGINT
columns with an explicit `currency`. `tests/Unit/ArchitectureTest.php` fails the
build if a `*_minor` column is ever declared `decimal`/`float`/`double`.

`round()` *is* used in `App\Domain`, deliberately: minor-units x a decimal
quantity has to collapse back to an integer somewhere, and `(int) round(...)`
is that place. What must never happen is a float-typed money *value* or a
float money *column* — the one loses precision permanently, the other silently.
(An earlier version of this file claimed an architecture test banned `round()`
outright. No such test existed, and the rule was never followed, because
`SalesService` cannot multiply a price by 1.5 units without it.)

**Stock is a ledger, not a number.** `stock_movements` is append-only —
never updated, never deleted. `stock_levels` is a derived cache maintained in
the same transaction, and `stock:reconcile` proves the two agree. Everything
writes through `StockService::apply()`; nothing else inserts movements.

**Void, never delete.** Transactional records are corrected by writing
compensating entries. Soft deletes belong only on catalog entities.

**The server owns pricing.** The client never supplies a line total or a grand
total. (The system this replaces trusted browser-computed totals.)

**Tenant isolation is tested, not assumed.** `TenancyIsolationTest` walks
every class in `App\Models\Tenant` and asserts its table exists in the shop's
database and does *not* exist centrally — so a model added later is covered
without anyone remembering to add a test.

## Engineering rules (Lorapok Labs)

- Boring + verifiable > clever + fragile.
- Evidence > assertion — if it isn't run and observed, it isn't done.
- Idempotent output: re-running a command must be safe.
- No hardcoded paths, no hardcoded product name or domain
  (`config('app.name')`, `LORAPOK_ROOT_DOMAIN`).
- No plaintext secrets in the repo.

## Design system

Mission Control tokens, copied **byte-for-byte** from
`/home/maizied/cursor-usage-monitor/website/shared/tokens.css` into
`resources/css/tokens.css`. Do not rename tokens or invent new colors —
re-copy the file to update it.

Primary accent is violet `#9075ff` — `Tenant::ACCENT_PALETTE[0]`, never a
literal. It replaced `#7c5cff`, on which **no text reached WCAG AA**: 4.35:1
with white, 4.11:1 with ink. `App\Support\Contrast` picks the label colour for
a shop's accent at render time and injects `--color-on-accent`; `ContrastTest`
holds every palette entry to AA as a button fill *and* as text on the surface.

`.glass-panel` is the canonical surface. Motion comes from `animations.css`
(`animate-fade-slide-up`, `.stagger-1..4`, `.shimmer`, `animate-mesh`) — there is
no Framer Motion here, it's React-only. The `prefers-reduced-motion` block is a
hard requirement.

**Tailwind v4 needs explicit `@source` lines** for Blade and PHP. Omitting them
drops utilities silently in production builds.

Accessibility floor: skip link first in tab order · never `outline: none`
without a replacement · POS fully keyboard-operable · `--color-muted` is not for
text under 14px and never for prices or quantities (use `--color-text`) ·
`--color-neon` is decorative only, never a status anyone must read.

## Layout

- `database/migrations/` — central DB only
- `database/migrations/tenant/` — per-shop DBs
- No data backfills inside tenant migrations; they run once per tenant on every
  deploy. Backfills go in queued jobs.
