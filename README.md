<div align="center">

# Lorapok Retail

**Multi-tenant inventory and point-of-sale, built for shops that sell serialised goods.**

One super admin provisions shops. Each shop gets its own subdomain, its own
branding, and — importantly — **its own database**.

[![CI](https://github.com/Maijied/Lorapok-Inventory-System/actions/workflows/ci.yml/badge.svg)](https://github.com/Maijied/Lorapok-Inventory-System/actions/workflows/ci.yml)
[![Laravel 13](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![Livewire 4](https://img.shields.io/badge/Livewire-4-4E56A6)](https://livewire.laravel.com)
[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

A product of **[Lorapok Labs](https://lorapok.tech)** · Dhaka, Bangladesh

</div>

---

## What this is

A phone shop sells a ৳45,000 handset with an IMEI, a ৳200 cable with no serial
at all, and takes half the payment in cash and half on bKash. It needs to know,
at the end of the day, how much it actually *made* — not how much it took.

Lorapok Retail is built around that. The parts that matter:

**Stock is a ledger, not a number.** `stock_movements` is append-only: never
updated, never deleted. `stock_levels` is a derived cache maintained in the same
transaction, and `php artisan stock:reconcile` proves the two agree and exits
non-zero if they ever drift. You can always answer "why is this number 7?".

**Margin is computable.** Every sale line freezes the weighted-average cost at
the moment of sale. Restocking cheaper next week cannot retrospectively change
what last week's sales earned.

**The server owns pricing.** The client never supplies a line total or a grand
total. A cashier without `manage_prices` can send any price they like; it is
ignored, not rejected.

**Money is never a float.** Integer minor units in `*_minor` columns with an
explicit currency. An architecture test fails the build if a money column is
ever declared `decimal` or `float`.

**Void, never delete.** Transactional records are corrected with compensating
entries, so a reversal leaves a trail instead of a hole.

**Isolation is tested, not assumed.** The test suite provisions real MySQL
databases rather than faking tenancy, because the property under test *is* the
database boundary — and it walks every tenant model to assert its table does not
exist centrally.

## Stack

| | |
|---|---|
| Framework | Laravel 13, Livewire 4 (single-file components) |
| Frontend | Tailwind v4 (CSS-first), self-hosted fonts, no CDN |
| Database | MySQL 8.4, one schema per shop via `stancl/tenancy` |
| Cache / queue | Redis |
| Tests | Pest 4 · PHPStan level 5 · Laravel Pint |
| Local | Laravel Sail (Docker) |

## Quick start

Requires Docker. **The host PHP is not used** — everything runs in containers.

```bash
git clone https://github.com/Maijied/Lorapok-Inventory-System.git
cd Lorapok-Inventory-System/lorapok-retail
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install && ./vendor/bin/sail npm run build
```

Browsers resolve `*.localhost` automatically; if yours does not, add to `/etc/hosts`:

```
127.0.0.1 lorapok.localhost demo.lorapok.localhost
```

- Operator panel — <http://lorapok.localhost/admin/login>
- A shop — <http://demo.lorapok.localhost>

### Create your first shop

```bash
./vendor/bin/sail artisan tinker --execute='
app(App\Domain\Central\ShopProvisioner::class)->create(
    name: "Demo Shop",
    slug: "demo",
    ownerName: "Owner",
    ownerEmail: "owner@demo.test",
    ownerPassword: "password12",
);'
```

The database is created, migrated, seeded with roles and permissions, and the
subdomain starts serving — all from that one call.

## Tests

```bash
./vendor/bin/sail artisan test              # everything
./vendor/bin/sail pest tests/Unit           # architecture + money, no database
./vendor/bin/sail artisan stock:reconcile   # ledger and cache agree
```

The tenancy suite creates and drops real databases, and CI fails if a run leaks
one.

## Layout

```
lorapok-retail/
├── app/
│   ├── Domain/            # the business rules — Stock, Sales, Purchasing,
│   │                      # Reporting, Central. No HTTP in here.
│   ├── Models/Tenant/     # per-shop models
│   └── Support/Money.php  # integer minor units
├── database/migrations/   # central database only
│   └── tenant/            # per-shop databases
├── resources/views/components/
│   ├── central/           # operator panel
│   └── tenant/            # the shop itself (⚡ = Livewire single-file)
└── tests/
```

See [`lorapok-retail/CLAUDE.md`](lorapok-retail/CLAUDE.md) for the engineering
conventions — including the ones that are load-bearing rather than stylistic.

## Status

Phases 0–9 are shipped: multi-tenancy, auth and roles, catalogue, stock ledger,
purchasing, sales and returns, POS, reporting, the operator panel, and an
installable PWA.

In progress: application shell and navigation, a product design system, brand
assets, deployment, the marketing site, billing, KYC, in-app help, and signed
release artifacts.

## Licence

MIT — see [LICENSE](LICENSE).
