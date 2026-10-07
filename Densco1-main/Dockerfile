FROM php:8.2-apache

# Disable conflicting MPMs so only the default prefork module runs
RUN a2dismod mpm_event mpm_worker || true

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

RUN docker-php-ext-install mysqli

EXPOSE 80

CMD ["apache2-foreground"]