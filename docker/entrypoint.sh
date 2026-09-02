#!/bin/sh
# PathwayTT container entrypoint.
#
# Every service (web, queue, scheduler) runs through this. It:
#   1. waits for MySQL,
#   2. guarantees an APP_KEY (persisted in the storage volume if none given),
#   3. on the WEB container only: migrates, seeds once, links storage,
#   4. execs the service command.
set -e

cd /var/www/html

# ── 1. Wait for the database ─────────────────────────────────────────
DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-3306}"
echo "[entrypoint] waiting for MySQL at ${DB_HOST}:${DB_PORT} ..."
i=0
until php -r "exit(@fsockopen('${DB_HOST}', ${DB_PORT}, \$e, \$s, 1) ? 0 : 1);"; do
    i=$((i + 1))
    if [ "$i" -ge 60 ]; then
        echo "[entrypoint] MySQL did not become reachable in time" >&2
        exit 1
    fi
    sleep 2
done

# ── 2. APP_KEY ───────────────────────────────────────────────────────
# Prefer the key passed in from the host's .env (docker-compose.yml reads
# it). Otherwise generate once and keep it in the storage volume so
# sessions and encrypted data survive container recreation.
KEY_FILE="storage/app/.docker-app-key"
if [ -z "$APP_KEY" ]; then
    if [ ! -s "$KEY_FILE" ]; then
        php artisan key:generate --show > "$KEY_FILE"
        echo "[entrypoint] generated an APP_KEY and stored it in ${KEY_FILE}"
    fi
    APP_KEY="$(cat "$KEY_FILE")"
    export APP_KEY
fi

# ── 3. Web container: schema, seed, storage link ─────────────────────
if [ "$1" = "apache2-foreground" ]; then
    mkdir -p storage/app/private storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
    if [ "$(id -u)" = "0" ]; then
        chown -R www-data:www-data storage bootstrap/cache
    fi

    run_as_app() {
        if [ "$(id -u)" = "0" ]; then
            su -s /bin/sh www-data -c "$*"
        else
            sh -c "$*"
        fi
    }

    echo "[entrypoint] running migrations"
    run_as_app "php artisan migrate --force --no-interaction"

    SEED_MARKER="storage/app/.seeded"
    if [ "${SEED_ON_FIRST_RUN:-true}" = "true" ] && [ ! -f "$SEED_MARKER" ]; then
        echo "[entrypoint] first run: seeding reference data (+ demo data when APP_ENV=local)"
        run_as_app "php artisan db:seed --force --no-interaction"
        run_as_app "touch $SEED_MARKER"
    fi

    run_as_app "php artisan storage:link --force >/dev/null 2>&1 || true"
    run_as_app "php artisan optimize:clear >/dev/null"
fi

echo "[entrypoint] starting: $*"
exec "$@"
