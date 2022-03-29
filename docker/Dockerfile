ARG PHP_VERSION=7.4
FROM php:${PHP_VERSION}-cli-alpine

RUN docker-php-ext-install -j$(getconf _NPROCESSORS_ONLN) \
        pdo_mysql \
        mysqli

RUN set -ex \
    && apk add --no-cache --virtual build-dependencies \
        autoconf \
        make \
        g++ \
    && pecl install -o xdebug && docker-php-ext-enable xdebug \
    && apk del build-dependencies

ARG COMPOSER_VERSION=2.2.6
RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin --filename=composer --version=$COMPOSER_VERSION
