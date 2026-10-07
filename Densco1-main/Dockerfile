FROM php:8.2-apache

# Ensure only mpm_prefork is enabled
RUN a2dismod mpm_event mpm_worker || true \
    && a2enmod mpm_prefork

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

RUN docker-php-ext-install mysqli

EXPOSE 80

CMD ["apache2-foreground"]