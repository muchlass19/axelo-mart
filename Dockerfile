# Axelo Mart - image produksi (Apache + mod_php, PHP 8.4). Listen di port 80.
FROM php:8.4-apache-bookworm

# Ekstensi PHP yang dibutuhkan Laravel + project ini.
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql gd intl zip bcmath exif

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache: document root ke /public, aktifkan mod_rewrite (untuk .htaccess Laravel).
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && a2enmod rewrite headers \
    && echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf && a2enconf servername \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-axelo.ini

WORKDIR /var/www/html

# Dependency dulu (cache layer), lalu source code.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/axelo-entrypoint
RUN chmod +x /usr/local/bin/axelo-entrypoint

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

EXPOSE 80
ENTRYPOINT ["axelo-entrypoint"]
CMD ["apache2-foreground"]
