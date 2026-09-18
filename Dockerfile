FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts

FROM php:8.4-fpm-alpine
RUN apk add --no-cache icu-dev libzip-dev postgresql-dev supervisor nginx \
    && docker-php-ext-install intl pdo_pgsql zip opcache
WORKDIR /var/www/html
COPY --from=vendor /app/vendor ./vendor
COPY . .
RUN chown -R www-data:www-data storage bootstrap/cache || true
COPY docker/supervisor/supervisord.conf /etc/supervisord.conf
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
EXPOSE 8080
CMD ["supervisord","-c","/etc/supervisord.conf"]
