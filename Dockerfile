# syntax=docker/dockerfile:1

#FROM composer:lts as prod-deps
#WORKDIR /app

# If your composer.json file defines scripts that run during dependency installation and
# reference your application source files, uncomment the line below to copy all the files
# into this layer.
# COPY . .

# Download dependencies as a separate step to take advantage of Docker's caching.
# Leverage a bind mounts to composer.json and composer.lock to avoid having to copy them
# into this layer.
# Leverage a cache mount to /tmp/cache so that subsequent builds don't have to re-download packages.
#RUN --mount=type=bind,source=composer.json,target=composer.json \
#    --mount=type=bind,source=composer.lock,target=composer.lock \
#    --mount=type=cache,target=/tmp/cache \
#    composer install --no-dev --no-interaction
#
#FROM composer:lts as dev-deps
#WORKDIR /app
#RUN --mount=type=bind,source=./composer.json,target=composer.json \
#    --mount=type=bind,source=./composer.lock,target=composer.lock \
#    --mount=type=cache,target=/tmp/cache \
#    composer install --no-interaction

################################################################################

FROM php:8.1-apache as base

# Use the default production configuration for PHP runtime arguments, see
# https://github.com/docker-library/docs/tree/master/php#configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions
RUN install-php-extensions bcmath gd intl json mbstring opcache xml zip uuid yaml
RUN install-php-extensions @composer-2 redis-6.0.2

RUN apt-get update && apt-get install -y iputils-ping git vim sane

#Apache conf
RUN a2enmod rewrite
COPY ./etc/apache2/docker-backend.conf /etc/apache2/sites-enabled/000-default.conf
COPY ./etc/sane.d/dll.conf /etc/sane.d/dll.conf
COPY ./etc/sane.d/net.conf /etc/sane.d/net.conf

FROM base as dev
RUN install-php-extensions xdebug
RUN --mount=type=bind,source=bin/symfony_installer,target=bin/symfony_installer \
    bash bin/symfony_installer
RUN mv /root/.symfony5/bin/symfony /usr/local/bin/symfony

RUN echo "xdebug.mode=develop,debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
& echo "xdebug.start_with_request=trigger" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
& echo "xdebug.discover_client_host=0" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
& echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
& echo "xdebug.idekey=PHPSTORM" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
#& echo "xdebug.client_port=9001" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
& echo "xdebug.log=/var/www/html/var/log/xdebug.log" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
& echo "xdebug.max_nesting_level=256" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
& echo "xdebug.log_level=1" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

COPY ./etc/php/php.ini-development "$PHP_INI_DIR/php.ini"
EXPOSE 9001
EXPOSE 9003
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN chown www-data:www-data /var/www

USER www-data

RUN mkdir -p var/log
RUN chown -R www-data:www-data var

#COPY --from=dev-deps app/vendor/ /var/www/html/vendor

FROM base as pre-prod
# The default config can be customized by copying configuration files into the $PHP_INI_DIR/conf.d/ directory.
#COPY ./etc/php/php.ini-production "$PHP_INI_DIR/php.ini"

# Switch to a non-privileged user (defined in the base image) that the app will run under.
# See https://docs.docker.com/go/dockerfile-user-best-practices/
USER www-data

# Copy the app files from the app directory.
COPY ./bin /var/www/html/bin
COPY ./config /var/www/html/config
COPY ./etc /var/www/html/etc
COPY ./public /var/www/html/public
COPY ./src /var/www/html/src
COPY ./templates /var/www/html/templates
RUN mkdir -p var/log
RUN chown -R www-data:www-data var

COPY ./.env /var/www/html/.env
COPY ./.env.local /var/www/html/.env.local
COPY ./composer.json /var/www/html/composer.json
COPY ./composer.lock /var/www/html/composer.lock

FROM pre-prod as test

#COPY ./tests /var/www/html/tests
COPY ./.env.test* /var/www/html/
#COPY ./phpunit.xml.dist /var/www/html/phpunit.xml

USER root
RUN chown -R www-data:www-data /var/www/html
USER www-data

RUN --mount=type=cache,target=/tmp/cache \
    composer install --no-dev --optimize-autoloader --no-interaction
RUN php bin/console assets:install

FROM pre-prod as prod

COPY ./.env.prod* /var/www/html/

USER root
RUN chown -R www-data:www-data /var/www/html
USER www-data

RUN --mount=type=cache,target=/tmp/cache \
    composer install --no-dev --optimize-autoloader --no-interaction
RUN php bin/console assets:install
