FROM php:8.2-apache

# Install PHP extensions
RUN docker-php-ext-install mysqli

# Remove default MPM config load conflicts
RUN rm -f /etc/apache2/mods-enabled/mpm_*

# Enable prefork MPM cleanly
RUN echo "LoadModule mpm_prefork_module /usr/lib/apache2/modules/mod_mpm_prefork.so" > /etc/apache2/mods-enabled/mpm_prefork.load

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]