FROM php:8.2-apache

# MySQL support (config.php uses mysqli)
RUN docker-php-ext-install mysqli curl

# Render gives the app a port in $PORT (default 10000) - make Apache listen on it
ENV PORT=10000
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
 && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf

# Copy the website (it lives in the Densco1-main folder of the repo)
COPY Densco1-main/ /var/www/html/

# Let PHP save uploaded product images
RUN mkdir -p /var/www/html/assets/products \
 && chown -R www-data:www-data /var/www/html/assets

EXPOSE 10000