# syntax=docker/dockerfile:1

# =============================================================================
# deploy multi-stage build
#
#   composer-bin  -> provides the Composer binary
#   assets        -> builds the front-end (Vite) into public/build
#   base          -> shared PHP-FPM runtime (extensions, system deps, user)
#   vendor        -> production Composer dependencies
#   vendor-dev    -> Composer dependencies including dev (for testing)
#
# Final targets: production | dev | testing
# =============================================================================

# ---- Composer binary --------------------------------------------------------
FROM composer:2.9 AS composer-bin

# ---- Front-end assets -------------------------------------------------------
# A glibc image is used (not alpine) so the prebuilt lightningcss / tailwind
# oxide native binaries resolve correctly.
FROM node:24-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN --mount=type=cache,target=/root/.npm npm ci
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# ---- Shared PHP-FPM runtime -------------------------------------------------
FROM php:8.5-fpm AS base
WORKDIR /app

# Single cached download, already executable (no chmod/sync step needed).
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
    bcmath \
    gd \
    intl \
    opcache \
    pcntl \
    pdo_mysql \
    zip

RUN apt-get update -y \
    && apt-get install -y --no-install-recommends sendmail unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer

# Create a runtime user whose UID/GID can mirror the host, so bind-mounted
# files in development keep sane ownership.
ARG HOST_USER_ID=1000
ARG HOST_GROUP_ID=1000
RUN if getent group ${HOST_GROUP_ID} >/dev/null; then \
        useradd -r -u ${HOST_USER_ID} -g ${HOST_GROUP_ID} -m -d /home/dockeruser dockeruser; \
    else \
        groupadd -g ${HOST_GROUP_ID} dockeruser \
        && useradd -r -u ${HOST_USER_ID} -g ${HOST_GROUP_ID} -m -d /home/dockeruser dockeruser; \
    fi

# ---- Production vendor ------------------------------------------------------
FROM base AS vendor
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && composer clear-cache

# ---- Vendor including dev dependencies (testing) ----------------------------
FROM base AS vendor-dev
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    composer install --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer install --optimize-autoloader --no-interaction

# ---- Production image -------------------------------------------------------
# FrankenPHP serves the app directly on :80 (single container, no fpm/nginx
# split). The image also ships git + the docker CLI/buildx/compose plugins so
# the queue worker can run docker_deploy steps against a mounted docker socket.
#
# Runs as root on purpose: the web container binds :80, and the worker needs
# the host's docker socket (root-equivalent by definition). Deployed apps run
# in their own containers; put the UI behind Cloudflare Access or similar.
FROM dunglas/frankenphp:1-php8.5 AS production
WORKDIR /app

RUN install-php-extensions \
    bcmath \
    gd \
    intl \
    opcache \
    pcntl \
    pdo_mysql \
    zip

RUN apt-get update -y \
    && apt-get install -y --no-install-recommends ca-certificates curl git \
    && install -m 0755 -d /etc/apt/keyrings \
    && curl -fsSL https://download.docker.com/linux/debian/gpg -o /etc/apt/keyrings/docker.asc \
    && chmod a+r /etc/apt/keyrings/docker.asc \
    && echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/debian $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
        > /etc/apt/sources.list.d/docker.list \
    && apt-get update -y \
    && apt-get install -y --no-install-recommends docker-ce-cli docker-buildx-plugin docker-compose-plugin \
    && rm -rf /var/lib/apt/lists/*

ENV APP_ENV=production
# The default Caddyfile serves /app/public on whatever SERVER_NAME says.
ENV SERVER_NAME=:80
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY --chmod=0755 .docker/entrypoint.sh /usr/local/bin/app-entrypoint

EXPOSE 80
ENTRYPOINT ["app-entrypoint"]
# Args for `frankenphp run` (docker-php-entrypoint prepends the binary).
CMD ["--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]

# ---- Development image ------------------------------------------------------
# Source code, vendor and node_modules are bind-mounted from the host
# (see docker-compose.yml), so nothing application-specific is baked in here.
FROM base AS dev
RUN install-php-extensions xdebug pcov
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
USER dockeruser
CMD ["php-fpm"]

# ---- Testing image ----------------------------------------------------------
FROM dev AS testing
USER root
COPY --chown=dockeruser . .
COPY --from=vendor-dev --chown=dockeruser /app/vendor ./vendor
COPY --from=assets --chown=dockeruser /app/public/build ./public/build
USER dockeruser
CMD ["vendor/bin/phpunit"]
