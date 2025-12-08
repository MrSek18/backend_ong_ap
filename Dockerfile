# Imagen base oficial de PHP con extensiones necesarias
FROM php:8.2-fpm

# Instalar dependencias del sistema y extensiones de PHP
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev zip unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Instalar Composer
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

# Crear directorio de la app
WORKDIR /var/www

# Copiar archivos del proyecto
COPY . .

# Instalar dependencias de Laravel (sin scripts para evitar fallos en build)
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Exponer puerto
EXPOSE 8000

# Comando de inicio (se define en Render)
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
