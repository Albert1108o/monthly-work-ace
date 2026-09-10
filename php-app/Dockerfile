FROM php:8.3-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends libsqlite3-dev sqlite3 \
 && docker-php-ext-install pdo pdo_sqlite \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY src/ /var/www/html/

ENV DB_DIR=/var/www/data
RUN mkdir -p /var/www/data && chown -R www-data:www-data /var/www/data /var/www/html

EXPOSE 80
