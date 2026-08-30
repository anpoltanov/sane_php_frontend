# syntax=docker/dockerfile:1

################################################################################
# Frontend assets (Encore). Copied into prod/test images only.
################################################################################
FROM node:20-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json webpack.config.js ./
COPY assets ./assets
RUN npm ci && npm run build

################################################################################
# PHP + Apache + SANE + Imagick
################################################################################
FROM php:8.3-apache AS base

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions gd intl mbstring opcache xml zip redis-6.0.2 imagick @composer-2

RUN apt-get update \
    && apt-get install -y --no-install-recommends sane imagemagick \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Allow PDF read/write so Imagick can export multi-page PDFs later.
RUN set -eux; \
    for policy in /etc/ImageMagick-6/policy.xml /etc/ImageMagick-7/policy.xml; do \
        if [ -f "$policy" ]; then \
            sed -i 's/rights="none" pattern="PDF"/rights="read|write" pattern="PDF"/' "$policy"; \
        fi; \
    done

RUN a2enmod rewrite
COPY ./etc/apache2/docker-backend.conf /etc/apache2/sites-enabled/000-default.conf
COPY ./etc/sane.d/dll.conf /etc/sane.d/dll.conf
COPY ./etc/sane.d/net.conf /etc/sane.d/net.conf

################################################################################
# Dev: Xdebug, Symfony CLI, extra tools (not in prod)
################################################################################
FROM base AS dev
RUN install-php-extensions xdebug
RUN apt-get update \
    && apt-get install -y --no-install-recommends git vim iputils-ping \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN --mount=type=bind,source=bin/symfony_installer,target=bin/symfony_installer \
    bash bin/symfony_installer
RUN if [ -f /root/.symfony5/bin/symfony ]; then mv /root/.symfony5/bin/symfony /usr/local/bin/symfony; \
    elif [ -f /root/.symfony/bin/symfony ]; then mv /root/.symfony/bin/symfony /usr/local/bin/symfony; fi

RUN echo "xdebug.mode=develop,debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
 && echo "xdebug.start_with_request=trigger" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
 && echo "xdebug.discover_client_host=0" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
 && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
 && echo "xdebug.idekey=PHPSTORM" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
 && echo "xdebug.log=/var/www/html/var/log/xdebug.log" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
 && echo "xdebug.max_nesting_level=256" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
 && echo "xdebug.log_level=1" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

COPY ./etc/php/php.ini-development "$PHP_INI_DIR/php.ini"
EXPOSE 9001
EXPOSE 9003
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN chown www-data:www-data /var/www

USER www-data

RUN mkdir -p var/log
RUN chown -R www-data:www-data var

################################################################################
# Shared app copy for test/prod
################################################################################
FROM base AS pre-prod

USER www-data

COPY ./bin /var/www/html/bin
COPY ./config /var/www/html/config
COPY ./etc /var/www/html/etc
COPY ./public /var/www/html/public
COPY ./src /var/www/html/src
COPY ./templates /var/www/html/templates
RUN mkdir -p var/log
RUN chown -R www-data:www-data var

COPY ./.env /var/www/html/.env
COPY ./composer.json /var/www/html/composer.json
COPY ./composer.lock /var/www/html/composer.lock

################################################################################
# Test image (includes PHPUnit)
################################################################################
FROM pre-prod AS test

COPY ./tests /var/www/html/tests
COPY ./phpunit.xml.dist /var/www/html/phpunit.xml.dist
COPY ./.env.test /var/www/html/.env.test
COPY --from=assets /app/public/build /var/www/html/public/build

USER root
RUN chown -R www-data:www-data /var/www/html
USER www-data

RUN --mount=type=cache,target=/tmp/cache \
    composer install --optimize-autoloader --no-interaction
RUN php bin/console assets:install --env=test

################################################################################
# Production image
################################################################################
FROM pre-prod AS prod

COPY --from=assets /app/public/build /var/www/html/public/build

USER root
RUN chown -R www-data:www-data /var/www/html
USER www-data

# Install while the copied .env still has APP_ENV=dev so Composer auto-scripts
# (cache:clear) use the filesystem cache and do not need Redis at build time.
RUN --mount=type=cache,target=/tmp/cache \
    composer install --no-dev --optimize-autoloader --no-interaction
RUN php bin/console assets:install --env=prod --no-debug

# Replace local-dev .env so the image does not ship APP_ENV=dev.
# Compose / systemd environment variables still override these values at runtime.
COPY --chown=www-data:www-data ./.env.prod /var/www/html/.env

ENV APP_ENV=prod
ENV APP_DEBUG=0

