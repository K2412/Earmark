#!/usr/bin/env sh
set -eu

: "${DB_DATABASE:=/var/www/html/database/database.sqlite}"

mkdir -p "$(dirname "$DB_DATABASE")" /var/www/html/storage /var/www/html/bootstrap/cache
touch "$DB_DATABASE"

php artisan migrate --force --no-interaction
php artisan db:seed --class=BucketSeeder --force --no-interaction

exec "$@"
