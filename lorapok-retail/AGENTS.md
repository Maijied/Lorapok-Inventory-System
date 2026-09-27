# Agent instructions

Read [CLAUDE.md](CLAUDE.md) first. It is the authoritative document for this
repository; this file only exists because tooling looks for `AGENTS.md`.

## The one rule that breaks everything if ignored

**Never run `php`, `composer` or `artisan` on the host.** The host PHP has `PDO`
but zero PDO drivers — no `pdo_mysql`, no `pdo_sqlite` — plus no `gd`, `bcmath`
or `redis`. Use Sail:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan …
./vendor/bin/sail pest
./vendor/bin/sail npm run build
```

> This file previously contained the stock Laravel Boost bootstrap, which told
> agents to verify `php -v` and run `php artisan boost:install` on the host.
> That directly contradicted CLAUDE.md and could not have worked here. Laravel
> Boost is not installed and is not planned.

## Before you finish

```bash
./vendor/bin/pint --test        # host is fine: no database
./vendor/bin/phpstan analyse    # level 5
./vendor/bin/sail artisan test
```

Do not edit a mounted file with `sed -i`: the rename replaces the inode and the
container stops seeing the file. Write in place instead.
