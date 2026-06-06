# ── Base: PHP 8.2 dengan Apache ────────────────────────────
FROM php:8.2-apache

LABEL maintainer="UAS Server Administration"
LABEL description="Web Dinamis — CRUD PHP Native + MariaDB"

# ── Install sistem dependencies ────────────────────────────
RUN apt-get update && apt-get install -y \
    libmariadb-dev-compat \
    libmariadb-dev \
    curl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ── Install & enable PHP extensions ───────────────────────
RUN docker-php-ext-install pdo pdo_mysql mysqli

# ── Enable Apache mod_rewrite (untuk URL bersih) ──────────
RUN a2enmod rewrite

# ── Konfigurasi Apache: izinkan .htaccess override ────────
RUN sed -i 's/AllowOverride None/AllowOverride All/g' \
    /etc/apache2/apache2.conf

# ── Copy source code aplikasi PHP ─────────────────────────
COPY --chown=www-data:www-data . /var/www/html/

# Hapus file non-PHP dari document root
RUN rm -f /var/www/html/Dockerfile \
    && rm -rf /var/www/html/sql

# ── Expose port 80 ────────────────────────────────────────
EXPOSE 80

# ── Jalankan Apache di foreground ─────────────────────────
CMD ["apache2-foreground"]
