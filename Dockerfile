FROM php:8.3-fpm

# Arguments defined in docker-compose.yml
ARG user=hrmanager
ARG uid=1000

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    nginx \
    supervisor \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip opcache

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create system user to run Composer and Artisan Commands
RUN useradd -G www-data,root -u $uid -d /home/$user $user \
    && mkdir -p /home/$user/.composer \
    && chown -R $user:$user /home/$user

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock ./

# Install composer dependencies
RUN composer install --no-scripts --no-autoloader --no-dev --optimize-autoloader

# Copy project files
COPY . .

# Generate autoloader and run scripts
RUN composer dump-autoload --optimize

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Create startup script
RUN echo '#!/bin/bash\n\
set -e\n\
\n\
# Wait for MySQL to be ready\n\
echo "Waiting for MySQL..."\n\
while ! nc -z mysql 3306; do\n\
  sleep 1\n\
done\n\
echo "MySQL is ready!"\n\
\n\
# Install dependencies if vendor directory is empty\n\
if [ ! -d "vendor" ] || [ -z "$(ls -A vendor 2>/dev/null)" ]; then\n\
    echo "Installing Composer dependencies..."\n\
    composer install --no-interaction --optimize-autoloader\n\
fi\n\
\n\
# Generate app key if not exists\n\
if [ -z "$(grep APP_KEY= .env | cut -d '=' -f2)" ]; then\n\
    echo "Generating application key..."\n\
    php artisan key:generate\n\
fi\n\
\n\
# Run migrations\n\
echo "Running migrations..."\n\
php artisan migrate --force\n\
\n\
# Run seeders\n\
echo "Running seeders..."\n\
php artisan db:seed --force\n\
\n\
# Create storage link\n\
echo "Creating storage link..."\n\
php artisan storage:link\n\
\n\
# Clear and cache configurations\n\
php artisan config:cache\n\
php artisan route:cache\n\
php artisan view:cache\n\
\n\
# Set proper permissions\n\
chown -R www-data:www-data storage bootstrap/cache\n\
chmod -R 775 storage bootstrap/cache\n\
\n\
echo "Application startup completed!"\n\
\n\
# Start PHP-FPM\n\
exec php-fpm\n\
' > /usr/local/bin/startup.sh && chmod +x /usr/local/bin/startup.sh

# Copy custom PHP-FPM configuration
RUN echo '[global]\n\
daemonize = no\n\
\n\
[www]\n\
user = www-data\n\
group = www-data\n\
listen = 9000\n\
listen.owner = www-data\n\
listen.group = www-data\n\
pm = dynamic\n\
pm.max_children = 20\n\
pm.start_servers = 5\n\
pm.min_spare_servers = 3\n\
pm.max_spare_servers = 10\n\
catch_workers_output = yes\n\
' > /usr/local/etc/php-fpm.d/www.conf

# Expose port 9000
EXPOSE 9000

# Use startup script as entrypoint
CMD ["/usr/local/bin/startup.sh"]
