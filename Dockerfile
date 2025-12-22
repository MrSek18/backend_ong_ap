FROM php:8.3-fpm

# Instalar dependencias del sistema + nginx
RUN apt-get update && apt-get install -y \
    nginx \
    git \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libzip-dev \
    zip \
    curl \
    gettext-base \
 && docker-php-ext-install pdo pdo_mysql mbstring zip gd \
 && rm -rf /var/lib/apt/lists/*

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Directorio de trabajo
WORKDIR /var/www/html

# Copiar el proyecto
COPY . .

# Permisos Laravel
RUN chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache


# Instalar dependencias PHP
RUN composer install --no-dev --optimize-autoloader

# Config nginx (template)
COPY nginx.template.conf /etc/nginx/nginx.template.conf

# Script de arranque
COPY docker-start.sh /usr/local/bin/docker-start.sh
RUN chmod +x /usr/local/bin/docker-start.sh

EXPOSE 8080

CMD ["docker-start.sh"]
