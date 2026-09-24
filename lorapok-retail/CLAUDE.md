# Lorapok Retail

Multi-tenant inventory & POS SaaS. A product of Lorapok Labs.
Super admin provisions shops; each shop gets its own subdomain, its own branding
and **its own database**.

`retail.lorapok.tech` · shops at `<shop>.retail.lorapok.tech`
Dev: `lorapok.localhost` · shops at `<shop>.lorapok.localhost`

## Running anything

**Never run `php` or `artisan` on the host.** The host PHP has `PDO` but *zero*
PDO drivers — no `pdo_mysql`, no `pdo_sqlite` — plus no `gd`, `bcmath` or
`redis`. Anything touching the database will fail with "could not find driver".

```
./vendor/bin/sail up -d
./vendor/bin/sail artisan …
./vendor/bin/sail pest
./vendor/bin/sail npm run dev
```

Docker Desktop must be running (`systemctl --user start docker-desktop`).

## Stack

Laravel 13 · Livewire 4 · Tailwind v4 (CSS-first) · MySQL 8.4 · Redis · Pest 4
Tenancy: `stancl/tenancy ^3.10`, **multi-database mode**.

## Non-negotiables

**Money is never a float.** Store integer minor units in `*_minor` BIGINT
columns with an explicit `currency`. No `float`, no `round()` in `App\Domain`.
There is an architecture test enforcing this.

**Stock is a ledger, not a number.** `stock_movements` is append-only —
never updated, never deleted. `stock_levels` is a derived cache maintained in
the same transaction, and `stock:reconcile` proves the two agree. Everything
writes through `StockService::apply()`; nothing else inserts movements.

**Void, never delete.** Transactional records are corrected by writing
compensating entries. Soft deletes belong only on catalog entities.

**The server owns pricing.** The client never supplies a line total or a grand
total. (The system this replaces trusted browser-computed totals.)

**Tenant isolation is tested, not assumed.** Every model gets an isolation
test; an architecture test fails the build if one is missing.

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

Primary accent is violet `#7c5cff`. (`~/.claude/skills/lorapok-frontend/SKILL.md`
lists accent/accent-2 swapped; the shipped `tokens.css` wins.)

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
