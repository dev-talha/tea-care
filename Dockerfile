# Stage 1: Build Tailwind CSS & Bundle Tabler Icons
FROM node:20-alpine AS assets
WORKDIR /build
COPY package.json tailwind.config.js ./
RUN npm install --no-audit --no-fund
COPY resources/css ./resources/css
COPY src/Views ./src/Views
COPY public/assets/js ./public/assets/js
RUN mkdir -p public/assets/css public/assets/tabler/fonts \
    && npm run build \
    && cp node_modules/@tabler/icons-webfont/dist/tabler-icons.min.css public/assets/tabler/tabler-icons.min.css \
    && cp node_modules/@tabler/icons-webfont/dist/fonts/* public/assets/tabler/fonts/

# Stage 2: Install PHP dependencies
FROM composer:2 AS vendor
WORKDIR /build
COPY composer.json ./
RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts --ignore-platform-reqs

# Stage 3: PHP-FPM Application & Scheduler
FROM php:8.2-fpm-alpine AS app
ENV TZ=Asia/Dhaka

RUN apk add --no-cache \
    tzdata \
    mariadb-client \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    gmp-dev \
    oniguruma-dev \
    curl-dev \
    && cp /usr/share/zoneinfo/Asia/Dhaka /etc/localtime \
    && echo "Asia/Dhaka" > /etc/timezone \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) pdo_mysql gd gmp mbstring opcache

WORKDIR /var/www/html

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-teacare.ini
COPY . .
COPY --from=vendor /build/vendor ./vendor
COPY --from=assets /build/public/assets/css/app.css ./public/assets/css/app.css
COPY --from=assets /build/public/assets/tabler ./public/assets/tabler

RUN mkdir -p storage/uploads storage/logs \
    && chown -R www-data:www-data storage \
    && chmod +x docker/entrypoint.sh bin/migrate.php bin/scheduler.php bin/generate-vapid.php

ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
CMD ["php-fpm"]

# Stage 4: Nginx Web Server
FROM nginx:alpine AS web
ENV TZ=Asia/Dhaka
RUN apk add --no-cache tzdata && cp /usr/share/zoneinfo/Asia/Dhaka /etc/localtime && echo "Asia/Dhaka" > /etc/timezone
WORKDIR /var/www/html
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public ./public
COPY --from=assets /build/public/assets/css/app.css ./public/assets/css/app.css
COPY --from=assets /build/public/assets/tabler ./public/assets/tabler
