# Imagen base con PHP y extensiones necesarias
FROM php:8.2-fpm

# Instalar dependencias de sistema y extensiones de PHP
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev zip unzip git curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql gd

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Establecer directorio de trabajo
WORKDIR /var/www/html

# Copiar código del backend
COPY . .

# Instalar dependencias de Laravel
RUN composer install --no-dev --optimize-autoloader

# Exponer puerto 80
EXPOSE 80

# Comando de inicio (puedes usar artisan serve o Nginx)
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=80"]
