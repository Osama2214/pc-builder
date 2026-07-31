#!/bin/sh
set -e

mkdir -p database
if [ ! -f database/database.sqlite ]; then
  touch database/database.sqlite
fi

php artisan config:clear
php artisan migrate --force
php artisan storage:link || true

PRODUCT_COUNT=$(sqlite3 database/database.sqlite "SELECT COUNT(*) FROM products;" 2>/dev/null || echo 0)
if [ "$PRODUCT_COUNT" = "0" ]; then
  echo "Products table is empty — seeding the real catalog..."
  php artisan db:seed --class=RealCatalogSeeder --force
fi

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
