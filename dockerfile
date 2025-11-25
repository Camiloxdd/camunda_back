# Imagen base con PHP 8.2
FROM php:8.2-fpm

# Instalar extensiones necesarias
RUN apt-get update && apt-get install -y \
    libzip-dev zip unzip git \
    && docker-php-ext-install pdo pdo_mysql

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Carpeta de trabajo
WORKDIR /app

# Copiar proyecto
COPY . .

# Instalar dependencias
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Exponer el servidor PHP embebido
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
