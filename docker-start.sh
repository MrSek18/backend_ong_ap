#!/usr/bin/env bash
set -e

echo "Using PORT=$PORT"

# Generar nginx.conf con el puerto correcto
envsubst '$PORT' < /etc/nginx/nginx.template.conf > /etc/nginx/nginx.conf

# Arrancar PHP-FPM
php-fpm -D

# Arrancar nginx (foreground)
nginx -g "daemon off;"
