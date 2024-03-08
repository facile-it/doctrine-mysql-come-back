ARG PHP_VERSION=8.1
FROM php:${PHP_VERSION}-cli-alpine

RUN docker-php-ext-install -j$(getconf _NPROCESSORS_ONLN) \
        pdo_mysql \
        mysqli

RUN set -ex \
    && apk add --no-cache --virtual build-dependencies \
        linux-headers \
        autoconf \
        make \
        g++ \
    && pecl install -o xdebug && docker-php-ext-enable xdebug \
    && apk del build-dependencies

# custom user
RUN adduser -D -u 1000 facile -G www-data \
    && mkdir -p /home/facile \
    && chown -R facile /home/facile
# zsh / OhMyZsh
RUN apk --no-cache add git zsh

RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin --filename=composer

USER facile
RUN sh -c "$(curl -fsSLv https://raw.githubusercontent.com/ohmyzsh/ohmyzsh/master/tools/install.sh)"
