FROM php:8.2-apache

# Install required PHP extensions
RUN docker-php-ext-install mysqli

# Purge any extra MPM module configurations except prefork
RUN rm -f /etc/apache2/mods-enabled/mpm_*.conf /etc/apache2/mods-enabled/mpm_*.load \
    && ln -s /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/ \
    && ln -s /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/

# Copy application files into Apache document root
COPY . /var/www/html/

# Set correct ownership
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]