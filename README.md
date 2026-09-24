# Lorapok Retail

Multi-tenant inventory & point-of-sale SaaS. A product of **Lorapok Labs**.

A super admin provisions shops; each shop gets its own subdomain, its own
branding and **its own database**.

The application lives in [`lorapok-retail/`](lorapok-retail/).
See [`lorapok-retail/CLAUDE.md`](lorapok-retail/CLAUDE.md) for architecture and
engineering conventions.

## Quick start

Requires Docker. The host PHP is not used — everything runs in containers.

```bash
cd lorapok-retail
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install && ./vendor/bin/sail npm run build
```

Then add to `/etc/hosts` (or rely on `*.localhost` resolution in Chrome):

```
127.0.0.1 lorapok.localhost demo.lorapok.localhost
```

- Central app: http://lorapok.localhost
- A shop: http://demo.lorapok.localhost

## Provisioning a shop

```bash
./vendor/bin/sail artisan tinker --execute='
$t = App\Models\Tenant::create(["name" => "Demo Shop", "slug" => "demo"]);
$t->domains()->create(["domain" => "demo.lorapok.localhost"]);
echo $t->id;'
```

The tenant database is created and migrated automatically.

## Tests

```bash
./vendor/bin/sail artisan test
```

The tenancy isolation suite provisions real MySQL databases rather than faking
tenancy, and drops them on teardown.
