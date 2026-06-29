#!/bin/bash
set -e

mkdir -p storage/app/public \
  storage/framework/cache \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache \
  public/storage

chmod -R 775 storage bootstrap/cache public/storage

if [ ! -e public/storage ]; then
  ln -s ../storage/app/public public/storage
fi

php artisan optimize:clear >/dev/null 2>&1 || true

echo "Hosting directories and permissions are ready."
