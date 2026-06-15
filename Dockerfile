# ============================================================
#  LAB COFFEE — PHP Backend Dockerfile
#  Base: php:8.2-apache
#  Target: Render.com Web Service (or any Docker host)
# ============================================================
FROM php:8.2-apache

# ── 1. System dependencies ───────────────────────────────────
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    pkg-config \
    libssl-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# ── 2. MongoDB PHP extension via PECL ───────────────────────
RUN pecl install mongodb \
    && docker-php-ext-enable mongodb

# ── 3. Other PHP extensions needed ──────────────────────────
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    zip

# ── 4. Install Composer ──────────────────────────────────────
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# ── 5. Enable Apache mod_rewrite (for .htaccess routing) ────
RUN a2enmod rewrite

# ── 6. Apache config: allow .htaccess overrides ─────────────
RUN sed -i 's/AllowOverride None/AllowOverride All/g' \
    /etc/apache2/apache2.conf

# ── 7. Set working directory ─────────────────────────────────
WORKDIR /var/www/html

# ── 8. Copy project files ────────────────────────────────────
COPY . /var/www/html/

# ── 9. Install Composer dependencies (production, no dev) ────
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --prefer-dist

# ── 10. Set correct Apache permissions ───────────────────────
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# ── 11. PHP production config ────────────────────────────────
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# ── 12. Custom PHP tuning ────────────────────────────────────
RUN echo "upload_max_filesize = 20M" >> "$PHP_INI_DIR/php.ini" \
    && echo "post_max_size = 20M" >> "$PHP_INI_DIR/php.ini" \
    && echo "memory_limit = 256M" >> "$PHP_INI_DIR/php.ini" \
    && echo "max_execution_time = 60" >> "$PHP_INI_DIR/php.ini"

# ── 13. Expose HTTP port ─────────────────────────────────────
EXPOSE 80

# ── 14. Apache foreground (default CMD from base image) ─────
CMD ["apache2-foreground"]
