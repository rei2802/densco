FROM php:8.2-apache

RUN docker-php-ext-install mysqli

# Ensure only mpm_prefork is enabled
RUN a2dismod mpm_event mpm_worker || true \
    && a2enmod mpm_prefork

COPY Densco1-main/ /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]