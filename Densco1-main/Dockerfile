FROM php:8.2-apache

# Install required MySQL extension
RUN docker-php-ext-install mysqli

# Remove ALL MPM module configurations from mods-enabled
RUN rm -f /etc/apache2/mods-enabled/mpm_*

# Enable ONLY mpm_prefork
RUN ln -s /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/ \
    && ln -s /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/

# Copy your application files into the document root
COPY . /var/www/html/

# Set proper ownership
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]