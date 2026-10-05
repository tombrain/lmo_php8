# Testumgebung fuer die Golden-Master-Tests.
# PHP-Version waehlbar:  PHP_VERSION=8.1 docker compose build tests
ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip faketime \
        libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd \
    && rm -rf /var/lib/apt/lists/*

# Xdebug als Coverage-Treiber (pcov stuerzt hier mit Segmentation fault ab).
# Standardmaessig aus: kein Overhead und keine zusaetzliche Ausgabe. Nur die Kindprozesse der
# Coverage-Messung starten mit -d xdebug.mode=coverage.
RUN pecl install xdebug && docker-php-ext-enable xdebug \
    && echo "xdebug.mode=off" > /usr/local/etc/php/conf.d/zz-xdebug-mode.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY docker/run-tests.sh /usr/local/bin/run-tests
RUN chmod +x /usr/local/bin/run-tests
ENTRYPOINT ["run-tests"]
