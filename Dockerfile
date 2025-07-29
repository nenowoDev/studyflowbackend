# Use a lightweight PHP-FPM image
FROM php:8.2-fpm-alpine

# Install system dependencies, PHP extensions, Nginx, and curl
RUN apk add --no-cache \
    nginx \
    mysql-client \
    php82-pdo_mysql \
    php82-gd \
    php82-dom \
    php82-xml \
    php82-json \
    php82-mbstring \
    php82-tokenizer \
    php82-openssl \
    curl

# Install Composer directly
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy php.ini for production
COPY --from=php:8.2-fpm-alpine /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Copy custom Nginx configuration to the main Nginx config file
# Ensure you have a 'docker' directory in your project root, and nginx.conf inside it.
COPY docker/nginx.conf /etc/nginx/nginx.conf

# Expose port 80 for Nginx (no 443 needed without SSL)
EXPOSE 80

# Define entrypoint to start PHP-FPM in the background and Nginx in the foreground
CMD php-fpm -D && nginx -g 'daemon off;'