FROM php:8.2-apache

# MySQL support (config.php uses mysqli)
RUN docker-php-ext-install mysqli

# Render gives the app a port in $PORT (default 10000) - make Apache listen on it
ENV PORT=10000
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
 && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf

# Install PHP dependencies (Cloudinary SDK) one level above the webroot,
# at /var/www/vendor. config/cloudinary.php loads it via
# `__DIR__ . '/../../vendor/autoload.php'` — two levels up from
# Densco1-main/config/ — so this mirrors the local repo layout, where
# vendor/ sits next to Densco1-main/. Without this step vendor/autoload.php
# never exists in the container and every Cloudinary upload (proof of
# payment, product images) fails.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy the website (it lives in the Densco1-main folder of the repo)
COPY Densco1-main/ /var/www/html/

# Let PHP save uploaded product images
RUN mkdir -p /var/www/html/assets/products \
 && chown -R www-data:www-data /var/www/html/assets

EXPOSE 10000