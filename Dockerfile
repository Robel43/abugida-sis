FROM php:8.1-apache

RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libicu-dev \
    libzip-dev \
    libxml2-dev \
    libonig-dev \
    gettext \
    unzip \
    curl \
    wkhtmltopdf \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        mysqli \
        pdo_mysql \
        intl \
        mbstring \
        gd \
        zip \
        gettext \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

COPY php.ini /usr/local/etc/php/conf.d/abugida.ini

WORKDIR /var/www/html