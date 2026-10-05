# Webserver zum manuellen Testen; lmo/ wird live eingebunden (siehe docker-compose.yml).
# PHP-Version waehlbar:  PHP_VERSION=8.1 docker compose up -d --build web
ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite

# Entwicklungs-Einstellungen: Fehler im Browser anzeigen
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    && printf 'date.timezone=Europe/Berlin\n' > "$PHP_INI_DIR/conf.d/zz-lmo.ini"

COPY docker/web-entrypoint.sh /usr/local/bin/lmo-web-entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/lmo-web-entrypoint && chmod +x /usr/local/bin/lmo-web-entrypoint
ENTRYPOINT ["lmo-web-entrypoint"]
CMD ["apache2-foreground"]
