FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libfreetype6-dev libjpeg62-turbo-dev libpng-dev libzip-dev \
    libicu-dev libxml2-dev libsodium-dev unzip git cron supervisor \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install -j$(nproc) mysqli pdo_mysql zip intl soap exif sodium opcache

RUN pecl install igbinary && docker-php-ext-enable igbinary \
    && pecl install redis && docker-php-ext-enable redis

RUN a2enmod rewrite

ENV APACHE_DOCUMENT_ROOT /var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY ./moodle-php.ini /usr/local/etc/php/conf.d/moodle.ini
RUN chown -R www-data:www-data /var/www/html

CMD ["apache2-foreground"]
