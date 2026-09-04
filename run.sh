#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

if ! docker compose version >/dev/null 2>&1; then
    echo "Docker with the compose plugin is required: https://docs.docker.com/get-docker/" >&2
    exit 1
fi

dc() { docker compose "$@"; }
in_app() { dc run --rm --no-deps -T app "$@"; }

bootstrap() {
    [ -f .env ] || cp .env.example .env

    dc build
    dc up -d mysql

    echo "Waiting for MySQL..."
    for _ in $(seq 1 60); do
        [ "$(dc ps mysql --format '{{.Health}}' 2>/dev/null)" = "healthy" ] && break
        sleep 2
    done

    in_app composer install --no-interaction --no-progress
    grep -q '^APP_KEY=.\+' .env || in_app php artisan key:generate
    dc run --rm -T app php artisan migrate --seed --force
}

case "${1:-up}" in
    up)
        bootstrap
        dc up -d app queue
        echo
        echo "API      http://localhost:${APP_PORT:-8000}/api"
        echo "Queue    running (docker compose logs -f queue)"
        echo "Example  curl 'http://localhost:${APP_PORT:-8000}/api/properties?city=Barcelona&check_in=2026-10-10&check_out=2026-10-15&guests=2'"
        ;;
    fresh)
        bootstrap
        dc run --rm -T app php artisan migrate:fresh --seed --force
        dc up -d app queue
        ;;
    test)
        [ -d vendor ] || bootstrap
        in_app php artisan test "${@:2}"
        ;;
    test:mysql)
        [ -d vendor ] || bootstrap
        dc up -d mysql
        dc exec -T mysql mysql -uroot -psecret \
            -e "CREATE DATABASE IF NOT EXISTS booking_test; GRANT ALL ON booking_test.* TO 'booking'@'%'; FLUSH PRIVILEGES;"
        dc run --rm -T \
            -e DB_CONNECTION=mysql -e DB_HOST=mysql -e DB_PORT=3306 \
            -e DB_DATABASE=booking_test -e DB_USERNAME=booking -e DB_PASSWORD=secret \
            app php artisan test "${@:2}"
        ;;
    check)
        [ -d vendor ] || bootstrap
        in_app composer check
        ;;
    artisan)
        dc run --rm -T app php artisan "${@:2}"
        ;;
    shell)
        dc run --rm app bash
        ;;
    logs)
        dc logs -f "${@:2}"
        ;;
    down)
        dc down
        ;;
    destroy)
        dc down -v
        ;;
    *)
        echo "Usage: ./run.sh [up|fresh|test|test:mysql|check|artisan <cmd>|shell|logs|down|destroy]" >&2
        exit 1
        ;;
esac
