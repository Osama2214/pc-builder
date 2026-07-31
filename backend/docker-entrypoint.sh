#!/bin/sh
set -e

php artisan config:clear
php artisan migrate --force
php artisan storage:link || true
php artisan catalog:seed-if-empty

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
