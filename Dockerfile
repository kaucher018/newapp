FROM php:8.4-fpm-alpine

# Install system dependencies & PHP extensions
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    nodejs \
    npm

RUN docker-php-ext-install pdo_mysql bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Install Frontend dependencies & Build assets
RUN npm install && npm run build

# Setup Nginx configuration
COPY ./docker/nginx.conf /etc/nginx/nginx.conf

# Setup Start script
RUN chmod +x ./docker/start.sh

EXPOSE 80

CMD ["./docker/start.sh"]