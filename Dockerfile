FROM php:8.2-fpm

# Kerakli PHP kengaytmalar va asboblar o'rnatiladi
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libsqlite3-dev \
    && docker-php-ext-install pdo_mysql pdo_sqlite mbstring exif pcntl bcmath gd zip

# Composer o'rnatish
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Node.js va npm o'rnatish (frontend uchun)
RUN curl -sL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g npm

# jprq o'rnatish
RUN curl -fsSL https://jprq.io/jprq-linux-amd64 -o /usr/local/bin/jprq \
    && chmod +x /usr/local/bin/jprq

# Ishchi direktoriya
WORKDIR /var/www

# Loyiha fayllarini ko'chirish
COPY . .

# Composer va npm paketlarini o'rnatish
RUN composer install --optimize-autoloader --no-dev \
    && npm install \
    && npm run build

# Huquqlarni sozlash
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage \
    && chmod -R 775 /var/www/storage /var/www/database

# PHP-FPM porti
EXPOSE 9000

CMD ["php-fpm"]