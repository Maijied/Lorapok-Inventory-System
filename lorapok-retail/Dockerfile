# syntax=docker/dockerfile:1

# Lorapok Retail production image.
#
# Three stages so the runtime carries neither Node nor Composer: the shipped
# layer holds PHP, the vendor tree and the built assets, nothing else.
#
# FrankenPHP rather than PHP-FPM behind nginx because Octane's worker mode is
# the single biggest latency win available here — the framework boots once per
# worker instead of once per request. On a till, that is the difference between
# a scan feeling instant and feeling like a wait.

# ─────────────────────────────── assets ────────────────────────────────
FROM node:22-alpine AS assets

WORKDIR /app

# Copy the manifests alone first: this layer is then cached until a dependency
# actually changes, rather than on every source edit.
COPY package.json package-lock.json ./
RUN npm ci

COPY resources ./resources
COPY vite.config.js ./
# Tailwind scans Blade and PHP for class names, so those directories have to be
# present at build time or the stylesheet ships nearly empty.
COPY app ./app
COPY config ./config

RUN npm run build

# ────────────────────────────── vendor ─────────────────────────────────
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

# --no-scripts because the application code is not here yet; artisan cannot
# run against a half-copied tree. Scripts run in the runtime stage.
#
# ext-pcntl is ignored here and only here: Horizon requires it, the official
# composer image does not ship it, and the runtime stage installs it
# explicitly. Naming the single extension rather than passing
# --ignore-platform-reqs keeps every other requirement enforced — a genuinely
# missing extension still fails this stage rather than the running container.
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --no-interaction \
        --prefer-dist \
        --ignore-platform-req=ext-pcntl

# The application code arrives only now, so the expensive download above stays
# cached across ordinary source edits. The autoloader is generated here rather
# than in the runtime stage because the runtime image has PHP but no Composer,
# and shipping Composer into the final layer to run one command is waste.
COPY . .

RUN composer dump-autoload \
        --no-dev \
        --optimize \
        --classmap-authoritative \
        --no-interaction

# ────────────────────────────── runtime ────────────────────────────────
FROM dunglas/frankenphp:php8.4 AS runtime

WORKDIR /app

# pdo_mysql is not optional: every request resolves a tenant and opens that
# shop's database. redis backs cache, session and the queue.
RUN install-php-extensions \
        pdo_mysql \
        redis \
        gd \
        bcmath \
        intl \
        zip \
        opcache \
        pcntl

# Opcache settings for a long-lived worker: the file cache is never revalidated
# because the image is immutable, and JIT is off — it costs memory and buys
# little for request-shaped work.
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=1'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.memory_consumption=192'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.jit=off'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# Application and vendor tree together, autoloader already generated.
COPY --from=vendor /app .
COPY --from=assets /app/public/build ./public/build

# package:discover is what --no-scripts skipped. It needs PHP, not Composer.
RUN php artisan package:discover --ansi

# Route and view caches are baked in; config is NOT. config:cache freezes
# env() at build time, and this image is deployed to environments whose
# database and Redis credentials are only known at run time.
RUN php artisan route:cache \
    && php artisan view:cache

# FrankenPHP runs as root by default; a web process has no need of it.
# Caddy (inside FrankenPHP) writes its own data and config. Without a
# writable home it logs "could not clean default/global storage" and "unable
# to autosave config" as ERRORs on every boot — harmless, but ERROR lines that
# are always there teach whoever operates this to stop reading the logs.
ENV XDG_DATA_HOME=/app/.caddy \
    XDG_CONFIG_HOME=/app/.caddy

RUN useradd --uid 10001 --create-home --shell /usr/sbin/nologin lorapok \
    && mkdir -p /app/.caddy \
    && chown -R lorapok:lorapok /app/storage /app/bootstrap/cache /app/.caddy \
    # Binding :80 as a non-root user needs the capability granted explicitly.
    && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp

USER lorapok

EXPOSE 80

# /up is Laravel's health endpoint and is deliberately outside every tenancy
# middleware, so it answers even when no shop resolves.
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1/up") !== false ? 0 : 1);'

CMD ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=80"]
