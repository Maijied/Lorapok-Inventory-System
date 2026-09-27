# Deployment

The pipeline is built, tested and **dormant**. With no credentials configured
the image still builds on every push to `main`, and the deploy step skips with
a warning rather than failing. Adding the secrets below is what turns it on.

Nothing here is committed to the repository, and nobody but you needs to see
any of these values.

## What runs where

| | |
|---|---|
| Application | Railway — one service, from the GHCR image |
| Database | Railway MySQL 8 |
| Cache, session, queue | Railway Redis |
| DNS, TLS, edge cache | Cloudflare |
| Image registry | GHCR (`ghcr.io/maijied/lorapok-inventory-system`) |

Shops live at `<shop>.lorapok.tech`. That is a **first-level** wildcard, which
Cloudflare's free Universal SSL covers — `*.retail.lorapok.tech` would not, and
would need Advanced Certificate Manager at roughly $10/month. Every new shop
therefore gets HTTPS the moment it is provisioned, at no cost.

## Secrets to set

Repository → Settings → Secrets and variables → Actions.

| Name | Where it comes from |
|---|---|
| `RAILWAY_TOKEN` | Railway → Account Settings → Tokens. **This one alone decides whether the deploy job runs at all.** |
| `CLOUDFLARE_API_TOKEN` | Cloudflare → My Profile → API Tokens. Scope it to *Zone → DNS → Edit* on `lorapok.tech` only. |
| `CLOUDFLARE_ZONE_ID` | Cloudflare dashboard, on the zone overview page. Not secret, but kept alongside the token. |

Set on the Railway service itself, not in GitHub:

| Name | Notes |
|---|---|
| `APP_KEY` | `php artisan key:generate --show`. **Changing it invalidates every session and every encrypted column.** |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` — a stack trace on a shop's screen exposes the database structure |
| `DB_*`, `REDIS_*` | Railway injects most of these from the attached services |
| `TENANCY_DB_USERNAME` / `PASSWORD` | A user with `CREATE DATABASE`. Provisioning a shop needs it; the application user deliberately does not have it. |
| `LORAPOK_ROOT_DOMAIN` | `lorapok.tech` |

## Cloudflare DNS

| Type | Name | Content | Proxy |
|---|---|---|---|
| CNAME | `lorapok.tech` | the Railway domain | on |
| CNAME | `*` | the Railway domain | on |

Then: SSL/TLS mode **Full (strict)**, Always Use HTTPS **on**, HSTS **on**.

Leave caching alone for now. The marketing site is cacheable at the edge but
the application is not — a cached POS page would show one shop's cart to
another, and that misconfiguration is not recoverable by clearing a cache after
the fact.

## First deploy

1. Set `RAILWAY_TOKEN`. Everything else can follow.
2. Push to `main`, or run the **Deploy** workflow manually.
3. The workflow builds the image, runs central migrations, releases, then
   queues tenant migrations.
4. The smoke test checks `/up` **and** that a tenant subdomain still serves. A
   deploy that answers the health check but 500s on every shop is not a
   successful deploy.

Tenant databases are migrated by a queued job rather than inline, so a release
does not block on however many shops exist — at fifty shops a synchronous
migration would hold the deploy for minutes while the old image is still live.

## Rolling back

```bash
railway redeploy --service app --yes   # redeploys the previous image
```

Migrations are **not** rolled back automatically, and mostly should not be.
Write a forward migration instead: rolling back a schema change under a live
till risks losing rows that were written between the deploy and the rollback.

## Why config is not cached in the image

`php artisan config:cache` freezes `env()` at **build** time. This image is
built once and deployed to an environment whose database and Redis credentials
are only known at run time, so caching config in the image would bake in
whatever the builder happened to have — which is nothing.

`route:cache` and `view:cache` **are** baked in: neither reads the environment.
