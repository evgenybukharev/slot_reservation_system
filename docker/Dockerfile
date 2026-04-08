FROM php:8.4-fpm

# Установка зависимостей
RUN apt-get update && apt-get install -y \
    build-essential \
    locales \
    zip \
    vim \
    unzip \
    git \
    curl \
    libonig-dev \
    libzip-dev \
    libxml2-dev

# Очистка кэша
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Установка расширений PHP
RUN docker-php-ext-install pdo_mysql mbstring zip exif pcntl

# Установка Redis расширения
RUN pecl install redis && docker-php-ext-enable redis

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Создание рабочей директории
WORKDIR /var/www

# Копирование существующих файлов приложения
COPY . /var/www

# Настройка прав доступа
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
