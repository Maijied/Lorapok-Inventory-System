# Self-hosting

For a shop chain that wants to run this on their own hardware rather than use
the hosted service. One machine with Docker installed.

## Getting it running

```
docker run --rm ghcr.io/maijied/lorapok-inventory-system:latest \
  php artisan key:generate --show
```

Put that in `.env` as `APP_KEY` and keep it. Changing it later invalidates
every session and makes every encrypted column unreadable — including the KYC
identifiers, which cannot be recovered.

Then set `DB_PASSWORD` and `LORAPOK_ROOT_DOMAIN`, and:

```
docker compose up -d
docker compose exec app php artisan migrate --force
```

## What you have to provide

**Wildcard DNS.** Shops are subdomains, so `*.yourdomain` must resolve to this
machine. Without it the operator panel works and no shop does.

**A wildcard certificate.** `*.yourdomain` — Let's Encrypt issues these over
DNS-01. A single-host certificate will not cover the shops.

**Backups that cover everything together.** Every shop has its own database.
A partial backup is worse than none: restoring the central database without a
shop's leaves a shop that exists, is billed, and cannot open.

```
docker compose exec mysql sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" \
  --all-databases --single-transaction --routines' > backup-$(date +%F).sql
```

`--single-transaction` matters. Without it a backup taken while a sale is
being rung up can capture the stock movement and miss the sale, and the ledger
will not reconcile on restore.

## Checking it is healthy

```
docker compose exec app php artisan stock:reconcile
curl -fsS http://localhost/up
```

`stock:reconcile` exits non-zero if any shop's cached stock levels disagree
with its movement ledger. Run it on a schedule — it is the one check that
tells you the numbers can still be trusted.
