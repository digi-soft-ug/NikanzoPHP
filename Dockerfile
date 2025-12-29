# syntax=docker/dockerfile:1

FROM php:8.3-cli-alpine

# Install PDO extensions (sqlite by default; mysql/pgsql optional)
RUN apk add --no-cache git unzip sqlite-dev oniguruma-dev libzip-dev \
    && docker-php-ext-install pdo pdo_sqlite \
    && apk del sqlite-dev oniguruma-dev libzip-dev

# Install Xdebug for code coverage
RUN apk add --no-cache $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && rm -rf /tmp/pear

# Install Composer
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm composer-setup.php

WORKDIR /var/www/html
COPY composer.json ./
RUN composer install --no-dev --prefer-dist --no-progress --no-interaction || true

COPY tools.sh /usr/local/bin/tools.sh
RUN chmod +x /usr/local/bin/tools.sh

COPY . .

# Default command: dev server on port 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "public/index.php"]