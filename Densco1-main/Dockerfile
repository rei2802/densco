FROM php:8.2-apache

# Install required MySQL extension
RUN docker-php-ext-install mysqli

# Copy application files into Apache root
COPY . /var/www/html/

# Set proper ownership
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

# Clean MPM modules at runtime startup right before Apache starts
CMD ["sh", "-c", "a2dismod mpm_event mpm_worker || true && a2enmod mpm_prefork && apache2-foreground"]